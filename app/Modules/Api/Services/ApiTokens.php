<?php

namespace App\Modules\Api\Services;

use App\Modules\Api\Models\ApiToken;
use Illuminate\Support\Str;

/** Creates and looks up API tokens. Only a hash is stored, so a lost token cannot be shown again, only replaced. */
class ApiTokens
{
    public const PREFIX = 'qrm_';

    /** @param list<string> $abilities @return array{token: ApiToken, plain: string} */
    public function issue(string $name, array $abilities, ?int $days = null): array
    {
        $plain = self::PREFIX.Str::random(40);
        $token = (new ApiToken(['name' => $name, 'prefix' => substr($plain, 0, 8), 'abilities' => array_values(array_intersect($abilities, ApiToken::ABILITIES)), 'expires_at' => $days ? now()->addDays($days) : null]))
            ->forceFill(['token_hash' => hash('sha256', $plain)]);
        $token->save();

        return ['token' => $token, 'plain' => $plain];
    }

    /** The token behind a bearer string, across all restaurants (the caller sets the tenant from it). */
    public function find(?string $plain): ?ApiToken
    {
        if (! $plain || ! str_starts_with($plain, self::PREFIX) || strlen($plain) > 80) {
            return null;
        }

        $token = ApiToken::withoutGlobalScopes()->where('token_hash', hash('sha256', $plain))->first();

        return $token && ! ($token->expires_at && $token->expires_at->isPast()) ? $token : null;
    }
}
