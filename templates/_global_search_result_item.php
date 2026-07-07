<?php
/**
 * Ligne de résultat recherche globale.
 *
 * @var array<string, mixed> $row
 */
$source = (string) ($row['source'] ?? '');
$url = (string) ($row['url'] ?? '#');
$displayLabel = (string) ($row['display_label'] ?? $row['titre'] ?? '');
$mediaLabel = (string) ($row['media_label'] ?? '');
$statutLabel = (string) ($row['statut_label'] ?? '');
?>
<li class="global-search-results__item" role="listitem">
    <a href="<?= Moncine\View::escape($url) ?>" class="global-search-results__link">
        <span class="global-search-results__label"><?= Moncine\View::escape($displayLabel) ?></span>
        <span class="global-search-results__meta">
            <?php if ($mediaLabel !== ''): ?>
                <span class="global-search-results__badge"><?= Moncine\View::escape($mediaLabel) ?></span>
            <?php endif; ?>
            <?php if ($source === 'library' && $statutLabel !== ''): ?>
                <span class="global-search-results__badge global-search-results__badge--muted">
                    <?= Moncine\View::escape($statutLabel) ?>
                </span>
            <?php elseif ($source === 'catalog'): ?>
                <span class="global-search-results__badge global-search-results__badge--muted">Catalogue</span>
            <?php endif; ?>
        </span>
    </a>
</li>
