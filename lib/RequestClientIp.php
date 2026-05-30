<?php
/**
 * Adresse IP du client (connexion directe ou derrière un reverse proxy).
 */

declare(strict_types=1);

namespace Moncine;

final class RequestClientIp
{
    public static function resolve(): string
    {
        $candidates = [];

        if (MONCINE_TRUST_PROXY) {
            $realIp = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
            if ($realIp !== '') {
                $candidates[] = $realIp;
            }

            $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
            if ($forwarded !== '') {
                foreach (explode(',', $forwarded) as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $candidates[] = $part;
                        break;
                    }
                }
            }
        }

        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remote !== '') {
            $candidates[] = $remote;
        }

        foreach ($candidates as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }

        return '0.0.0.0';
    }
}
