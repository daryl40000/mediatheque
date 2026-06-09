<?php
/**
 * Badge Linux — pingouin Tux (jeu PC testé sous Linux).
 *
 * @var string $size sm|md
 * @var bool $plain Variante compacte (listes, fiche)
 */
$size = $size ?? 'md';
$plain = !empty($plain);
$class = 'game-linux-badge game-linux-badge--' . ($size === 'sm' ? 'sm' : 'md');
if ($plain) {
    $class .= ' game-linux-badge--plain';
}
?>
<span class="<?= Moncine\View::escape($class) ?>" title="Testé sous Linux" aria-label="Testé sous Linux">
    <svg class="game-linux-badge__icon" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
        <ellipse cx="18" cy="34" rx="8" ry="14" fill="#2b2b2b" transform="rotate(-25 18 34)"/>
        <ellipse cx="46" cy="34" rx="8" ry="14" fill="#2b2b2b" transform="rotate(25 46 34)"/>
        <ellipse cx="32" cy="38" rx="16" ry="20" fill="#2b2b2b"/>
        <ellipse cx="32" cy="40" rx="10" ry="13" fill="#ffffff"/>
        <circle cx="32" cy="20" r="14" fill="#2b2b2b"/>
        <ellipse cx="32" cy="22" rx="8" ry="9" fill="#ffffff"/>
        <circle cx="28" cy="18" r="2.2" fill="#ffffff"/>
        <circle cx="36" cy="18" r="2.2" fill="#ffffff"/>
        <circle cx="28.4" cy="18.4" r="1.1" fill="#2b2b2b"/>
        <circle cx="36.4" cy="18.4" r="1.1" fill="#2b2b2b"/>
        <path fill="#f5a623" d="M32 21.5 29 25.5h6z"/>
        <ellipse cx="25" cy="56" rx="5" ry="2.5" fill="#f5a623"/>
        <ellipse cx="39" cy="56" rx="5" ry="2.5" fill="#f5a623"/>
    </svg>
</span>
