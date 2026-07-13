<?php
/**
 * Favicon, icônes PWA (Android) et raccourci iOS.
 * $headIconVersion : invalide le cache navigateur à chaque release.
 */
$headIconVersion = rawurlencode(MONCINE_PACKAGE_VERSION);
$iconQuery = '?v=' . Moncine\View::escape($headIconVersion);
?>
<link rel="icon" href="/favicon.ico<?= $iconQuery ?>" sizes="any">
<link rel="icon" href="/assets/img/favicon.ico<?= $iconQuery ?>" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32x32.png<?= $iconQuery ?>">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16x16.png<?= $iconQuery ?>">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/img/apple-touch-icon.png<?= $iconQuery ?>">
<link rel="manifest" href="/manifest.webmanifest<?= $iconQuery ?>">
<meta name="theme-color" content="#0f0f12">
<meta name="apple-mobile-web-app-title" content="<?= Moncine\View::escape(MONCINE_APP_NAME) ?>">
