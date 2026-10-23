<?php
declare(strict_types=1);

namespace TgWatch;

final class Http
{
    public const UA = 'Mozilla/5.0 (compatible; tgwatch/1.0; +https://t.me)';

    /**
     * @return array{status:int, body:string, error:string}
     */
    public static function request(string $method, string $url, array $headers = [], $body = null, int $timeout = 20): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => self::UA,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($method !== 'GET') {
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $resp === false ? curl_error($ch) : '';
        curl_close($ch);
        return ['status' => $status, 'body' => $resp === false ? '' : (string)$resp, 'error' => $error];
    }

    public static function get(string $url, int $timeout = 20): array
    {
        return self::request('GET', $url, ['Accept-Language: en'], null, $timeout);
    }

    public static function postJson(string $url, array $data, array $headers = [], int $timeout = 20): array
    {
        $headers[] = 'Content-Type: application/json';
        return self::request('POST', $url, $headers, json_encode($data, JSON_UNESCAPED_UNICODE), $timeout);
    }
}
