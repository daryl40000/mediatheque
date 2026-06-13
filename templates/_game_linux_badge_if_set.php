<?php
/**
 * Affiche le badge Linux si le statut est connu (testé ou non supporté).
 *
 * @var array<string, mixed> $game
 * @var string $size sm|md
 */
$linuxBadge = (string) ($game['linux_badge'] ?? '');
if ($linuxBadge === '' && !empty($game['tested_on_linux'])) {
    $linuxBadge = 'supported';
}
if ($linuxBadge === '' && !empty($game['linux_not_supported'])) {
    $linuxBadge = 'unsupported';
}
if ($linuxBadge === '') {
    return;
}

$linuxNotSupported = $linuxBadge === 'unsupported';
require MONCINE_ROOT . '/templates/_game_linux_badge.php';
