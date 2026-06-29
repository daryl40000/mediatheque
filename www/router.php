<?php
/**
 * Routeur pour le serveur PHP intégré (php -S) — réécrit /posters/*.jpg vers poster.php.
 * Apache/Nginx utilisent www/.htaccess à la place.
 */

declare(strict_types=1);

$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (!is_string($uri) || $uri === '') {
    $uri = '/';
}

if (preg_match('#^/posters/s(\d+)\.(jpe?g|png|webp)$#i', $uri, $matches)) {
    $_GET['series'] = (int) $matches[1];
    $_GET['ext'] = strtolower($matches[2]) === 'jpeg' ? 'jpg' : strtolower($matches[2]);
    require __DIR__ . '/poster.php';

    return true;
}

if (preg_match('#^/posters/(\d+)\.(jpe?g|png|webp)$#i', $uri, $matches)) {
    $_GET['id'] = (int) $matches[1];
    $_GET['ext'] = strtolower($matches[2]) === 'jpeg' ? 'jpg' : strtolower($matches[2]);
    require __DIR__ . '/poster.php';

    return true;
}

return false;
