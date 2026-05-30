<?php
/**
 * Polyfills mbstring pour les environnements où l'extension n'est pas installée.
 *
 * Moncine utilise plusieurs fonctions mb_* (UTF-8). Sur certaines installations locales,
 * mbstring n'est pas activé par défaut : on fournit des fallbacks raisonnables.
 *
 * Note : ces fallbacks ne couvrent pas toutes les subtilités Unicode, mais évitent
 * un crash et restent suffisants pour les usages courants (emails, filtres, etc.).
 */
declare(strict_types=1);

namespace Moncine;

function mb_strtolower(string $string, ?string $encoding = null): string
{
    if (\function_exists('\\mb_strtolower')) {
        return \mb_strtolower($string, $encoding ?? 'UTF-8');
    }

    return \strtolower($string);
}

function mb_strtoupper(string $string, ?string $encoding = null): string
{
    if (\function_exists('\\mb_strtoupper')) {
        return \mb_strtoupper($string, $encoding ?? 'UTF-8');
    }

    return \strtoupper($string);
}

function mb_strlen(string $string, ?string $encoding = null): int
{
    if (\function_exists('\\mb_strlen')) {
        return \mb_strlen($string, $encoding ?? 'UTF-8');
    }

    return \strlen($string);
}

function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
{
    if (\function_exists('\\mb_substr')) {
        return \mb_substr($string, $start, $length, $encoding ?? 'UTF-8');
    }

    return $length === null
        ? \substr($string, $start)
        : \substr($string, $start, $length);
}

