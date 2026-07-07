<?php
/**
 * Requêtes HTTP JSON pour les catalogues magasins (GOG, Epic).
 */

declare(strict_types=1);

namespace Moncine;

final class StoreCatalogHttp
{
    private const HTTP_TIMEOUT = 20;

    private const USER_AGENT = 'Moncine/1.0 (store catalog enrichment)';

    /**
     * @param list<string> $headers
     */
    public static function get(string $url, array $headers = [], ?string &$error = null): ?string
    {
        return self::request('GET', $url, null, $headers, $error);
    }

    /**
     * @param list<string> $headers
     */
    public static function postJson(string $url, string $jsonBody, array $headers = [], ?string &$error = null): ?string
    {
        $headers = array_merge(['Content-Type: application/json'], $headers);

        return self::request('POST', $url, $jsonBody, $headers, $error);
    }

    /**
     * @param list<string> $headers
     */
    private static function request(string $method, string $url, ?string $body, array $headers, ?string &$error): ?string
    {
        $error = null;
        $headers = array_merge(['Accept: application/json'], $headers);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                $error = 'Impossible d’initialiser cURL.';

                return null;
            }

            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_USERAGENT => self::USER_AGENT,
            ];
            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $body ?? '';
            }

            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                $error = 'Connexion impossible.';

                return null;
            }
            if ($code >= 400) {
                $error = 'HTTP ' . $code . '.';

                return null;
            }

            $response = (string) $response;
            if (self::looksLikeHtml($response)) {
                $error = self::htmlResponseError($code);

                return null;
            }

            return $response;
        }

        $headerString = implode("\r\n", $headers) . "\r\nUser-Agent: " . self::USER_AGENT . "\r\n";
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => self::HTTP_TIMEOUT,
                'header' => $headerString,
                'content' => $body ?? '',
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $error = 'Connexion impossible.';

            return null;
        }

        $response = (string) $response;
        if (self::looksLikeHtml($response)) {
            $error = self::htmlResponseError(200);

            return null;
        }

        return $response;
    }

    private static function looksLikeHtml(string $body): bool
    {
        $trimmed = ltrim($body);

        return $trimmed !== '' && (
            str_starts_with($trimmed, '<!DOCTYPE')
            || str_starts_with($trimmed, '<html')
            || str_contains($trimmed, 'cf_challenge')
        );
    }

    private static function htmlResponseError(int $code): string
    {
        if ($code >= 500) {
            return 'Le magasin a renvoyé une page d’erreur (HTTP ' . $code . '). Réessayez plus tard.';
        }

        return 'Réponse HTML inattendue (protection anti-robot ou page d’erreur).';
    }
}
