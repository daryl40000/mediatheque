<?php
/**
 * Une icône support / magasin (utilisé par _game_edition_icons.php).
 *
 * Image personnalisée : www/assets/img/game-editions/{cd_dvd|disquette|steam|gog|epic}.png
 * Si le fichier n’existe pas, un SVG simplifié est affiché.
 *
 * @var string $iconKey cd_dvd|disquette|steam|gog|epic
 */
$iconKey = (string) ($iconKey ?? '');
$class = 'game-edition-icon game-edition-icon--' . preg_replace('/[^a-z0-9_]+/', '', $iconKey);
$imageUrl = Moncine\GameEditionIcons::iconImageUrl($iconKey);
$label = Moncine\GameEditionIcons::label($iconKey);
$isVertical = $iconKey === Moncine\GameEditionIcons::DISKETTE;
$iconWidth = $isVertical ? 17 : 22;
$iconHeight = 22;
$viewBox = $isVertical ? '0 0 24 30' : '0 0 24 24';
?>
<?php if ($imageUrl !== ''): ?>
    <img class="<?= Moncine\View::escape($class) ?> game-edition-icon--img"
         src="<?= Moncine\View::escape($imageUrl) ?>"
         alt=""
         title="<?= Moncine\View::escape($label) ?>"
         width="<?= (int) $iconWidth ?>" height="<?= (int) $iconHeight ?>"
         loading="lazy" decoding="async">
<?php else: ?>
<svg class="<?= Moncine\View::escape($class) ?>"
     viewBox="<?= Moncine\View::escape($viewBox) ?>" aria-hidden="true" focusable="false">
    <?php if ($iconKey === Moncine\GameEditionIcons::CD_DVD): ?>
        <circle cx="12" cy="12" r="9" fill="#c8d0da"/>
        <circle cx="12" cy="12" r="2.2" fill="#5c6775"/>
        <circle cx="12" cy="12" r="9" fill="none" stroke="#8a96a8" stroke-width="1.2"/>
        <path d="M12 3v18" stroke="#9aa5b5" stroke-width="0.8" opacity="0.7"/>
        <path d="M3 12h18" stroke="#9aa5b5" stroke-width="0.8" opacity="0.7"/>
    <?php elseif ($iconKey === Moncine\GameEditionIcons::DISKETTE): ?>
        <rect x="4" y="1" width="16" height="28" rx="1.2" fill="#2a2f36"/>
        <rect x="4.5" y="1.5" width="15" height="27" rx="0.8" fill="#3a414a"/>
        <rect x="10.2" y="4.2" width="3.6" height="6.2" rx="1.8" fill="#6d7680"/>
        <circle cx="12" cy="13.5" r="3.8" fill="#b8c0c8"/>
        <circle cx="12" cy="13.5" r="1.1" fill="#5c6775"/>
        <circle cx="12" cy="10.2" r="0.55" fill="#8a96a8"/>
        <circle cx="14.5" cy="11.6" r="0.45" fill="#8a96a8"/>
        <circle cx="14.5" cy="15.4" r="0.45" fill="#8a96a8"/>
        <circle cx="12" cy="16.8" r="0.55" fill="#8a96a8"/>
        <circle cx="9.5" cy="15.4" r="0.45" fill="#8a96a8"/>
        <circle cx="9.5" cy="11.6" r="0.45" fill="#8a96a8"/>
        <rect x="5" y="18.8" width="14" height="8.8" fill="#f4f4f4"/>
        <rect x="5" y="18.8" width="14" height="2.2" fill="#d62828"/>
        <rect x="5" y="21" width="14" height="0.35" fill="#c8c8c8"/>
        <rect x="5" y="22.1" width="14" height="0.35" fill="#c8c8c8"/>
        <rect x="5" y="23.2" width="14" height="0.35" fill="#c8c8c8"/>
        <rect x="5" y="26.4" width="14" height="1.2" fill="#aeb6bf"/>
    <?php elseif ($iconKey === Moncine\GameEditionIcons::STEAM): ?>
        <circle cx="12" cy="12" r="10" fill="#1b2838"/>
        <circle cx="8.2" cy="14.2" r="2.4" fill="#66c0f4"/>
        <circle cx="15.8" cy="9.8" r="3.2" fill="#c7d5e0"/>
        <circle cx="15.8" cy="9.8" r="1.4" fill="#1b2838"/>
        <path d="M10.2 13.4 14.8 10.6" stroke="#66c0f4" stroke-width="1.4" stroke-linecap="round"/>
    <?php elseif ($iconKey === Moncine\GameEditionIcons::GOG): ?>
        <circle cx="12" cy="12" r="10" fill="#782cf5"/>
        <path fill="#fff" d="M8.5 12.2c0-2 1.6-3.6 3.6-3.6.9 0 1.7.3 2.3.9l-1.1 1.1a2 2 0 0 0-1.2-.4c-1.1 0-2 .9-2 2s.9 2 2 2c.5 0 .9-.2 1.2-.4l1.1 1.1c-.6.6-1.4.9-2.3.9-2 0-3.6-1.6-3.6-3.6z"/>
        <path fill="#fff" d="M13.8 10.8h3.7v1.2h-2.5v.9h2.2v1.2h-2.2v1.4h-1.2z"/>
    <?php elseif ($iconKey === Moncine\GameEditionIcons::EPIC): ?>
        <rect x="3" y="3" width="18" height="18" rx="4" fill="#2a2a2a"/>
        <path fill="#fff" d="M7.5 16.5 12 7.5l4.5 9z"/>
    <?php endif; ?>
</svg>
<?php endif; ?>
