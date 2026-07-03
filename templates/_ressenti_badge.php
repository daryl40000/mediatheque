<?php
/**
 * Badge ressenti (icône + libellé optionnel).
 *
 * @var int|null $score ressenti 1–5
 * @var bool $showLabel afficher le texte (ex. « J'adore »)
 * @var string $size default|small
 */
$score = isset($score) ? Moncine\RessentiNote::normalizeScore((int) $score) : null;
$showLabel = $showLabel ?? false;
$size = ($size ?? 'default') === 'small' ? 'small' : 'default';

if ($score === null) {
    echo '—';
    return;
}

$key = Moncine\RessentiNote::keyFromScore($score);
if ($key === null) {
    echo '—';
    return;
}
?>
<span class="ressenti-badge ressenti-badge--<?= Moncine\View::escape($size) ?> <?= Moncine\View::escape(Moncine\RessentiNote::cssClass($key)) ?>"
      title="<?= Moncine\View::escape(Moncine\RessentiNote::label($key)) ?>">
    <span class="ressenti-badge__icon" aria-hidden="true"><?= Moncine\RessentiNote::iconSvg($key) ?></span>
    <?php if ($showLabel): ?>
        <span class="ressenti-badge__label"><?= Moncine\View::escape(Moncine\RessentiNote::label($key)) ?></span>
    <?php endif; ?>
</span>
