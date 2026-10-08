<?php

namespace App\Modules\Auth\Services;

use App\Modules\Core\Services\SettingsService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "Sign in with Apple" without an extra package: builds the authorisation link, signs the client secret
 * (a short-lived ES256 token made from the .p8 key) and reads the identity out of the id_token Apple returns
 * directly over TLS. The state is an encrypted, expiring value, because Apple posts back from another site
 * and a session cookie would not be sent along.
 */
class AppleSignIn
{
    private const AUTH = 'https://appleid.apple.com/auth/authorize';

    private const TOKEN = 'https://appleid.apple.com/auth/token';

    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('auth.apple_enabled', false);
    }

    public function authorizeUrl(string $redirect): string
    {
        return self::AUTH.'?'.http_build_query([
            'client_id' => $this->settings->get('auth.apple_client_id'), 'redirect_uri' => $redirect, 'response_type' => 'code',
            'response_mode' => 'form_post', 'scope' => 'name email', 'state' => $this->makeState(),
        ]);
    }

    public function makeState(): string
    {
        return Crypt::encryptString(json_encode(['n' => Str::random(16), 'exp' => time() + 600]));
    }

    public function validState(?string $state): bool
    {
        try {
            $data = json_decode(Crypt::decryptString((string) $state), true);
        } catch (\Throwable) {
            return false;
        }

        return is_array($data) && ($data['exp'] ?? 0) >= time();
    }

    /** @return array{id: string, email: ?string} @throws RuntimeException */
    public function identity(string $code, string $redirect): array
    {
        $response = Http::asForm()->timeout(10)->post(self::TOKEN, [
            'client_id' => $this->settings->get('auth.apple_client_id'), 'client_secret' => $this->clientSecret(),
            'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => $redirect,
        ]);

        if (! $response->ok() || ! ($idToken = $response->json('id_token'))) {
            throw new RuntimeException('Apple did not accept the sign-in.');
        }

        $claims = $this->claims($idToken);

        if (($claims['iss'] ?? '') !== 'https://appleid.apple.com' || ($claims['aud'] ?? '') !== $this->settings->get('auth.apple_client_id') || ($claims['exp'] ?? 0) < time() || empty($claims['sub'])) {
            throw new RuntimeException('The Apple identity token is not valid.');
        }

        return ['id' => (string) $claims['sub'], 'email' => isset($claims['email']) ? strtolower((string) $claims['email']) : null];
    }

    /** @return array<string, mixed> payload of a JWT (read, not verified: it came straight from Apple's token endpoint) */
    public function claims(string $jwt): array
    {
        $parts = explode('.', $jwt);

        return count($parts) === 3 ? (json_decode($this->b64d($parts[1]), true) ?: []) : [];
    }

    public function clientSecret(): string
    {
        $header = ['alg' => 'ES256', 'kid' => (string) $this->settings->get('auth.apple_key_id'), 'typ' => 'JWT'];
        $now = time();
        $claims = ['iss' => (string) $this->settings->get('auth.apple_team_id'), 'iat' => $now, 'exp' => $now + 300, 'aud' => 'https://appleid.apple.com', 'sub' => (string) $this->settings->get('auth.apple_client_id')];
        $input = $this->b64e(json_encode($header)).'.'.$this->b64e(json_encode($claims));

        $key = openssl_pkey_get_private(str_replace('\n', "\n", (string) $this->settings->get('auth.apple_private_key')));

        if (! $key || ! openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The Apple private key is not valid.');
        }

        return $input.'.'.$this->b64e($this->derToRaw($der));
    }

    /** ES256 wants the two 32-byte integers r||s, OpenSSL gives an ASN.1 sequence. */
    private function derToRaw(string $der): string
    {
        $offset = 3;
        $rLen = ord($der[$offset]);
        $r = substr($der, $offset + 1, $rLen);
        $offset += $rLen + 2;
        $sLen = ord($der[$offset]);
        $s = substr($der, $offset + 1, $sLen);

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT).str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    private function b64e(string $v): string
    {
        return rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
    }

    private function b64d(string $v): string
    {
        return (string) base64_decode(strtr($v, '-_', '+/'));
    }
}
