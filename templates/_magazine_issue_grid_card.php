<?php
/**
 * Tuile numéro magazine (couverture + pied compact + bulle au survol).
 *
 * @var array<string, mixed> $row
 * @var array<string, mixed>|null $series
 * @var bool $showSeriesTitleInBubble
 * @var bool $isWishlist
 * @var bool $showFooter
 */
$row = $row ?? [];
$series = $series ?? null;
$showSeriesTitleInBubble = (bool) ($showSeriesTitleInBubble ?? false);
$isWishlist = (bool) ($isWishlist ?? false);
$showFooter = (bool) ($showFooter ?? true);

$bibId = (int) ($row['bib_id'] ?? 0);
$storedObjectId = (int) ($row['stored_object_id'] ?? 0);
$issueUrl = Moncine\View::magazineIssueUrl($bibId);
$pdfUrl = $storedObjectId > 0 ? '/media-object.php?id=' . $storedObjectId : '';
$cover = Moncine\View::posterSrc(trim((string) ($row['poster_url'] ?? '')) ?: null);
$isPossessed = Moncine\MagazineSupport::isPossessed($row);

$cardClass = 'magazine-issue-card';
if (!$isWishlist && !$isPossessed) {
    $cardClass .= ' magazine-issue-card--unowned';
}

$numeroLabel = (string) ($row['numero'] ?? '');
if (!empty($row['est_hors_serie'])) {
    $numeroLabel = 'HS ' . $numeroLabel;
}

// Possédé en papier : badge sur la jaquette (angle haut droit).
$hasPaper = !$isWishlist && Moncine\MagazineSupport::hasPaper((string) ($row['support_physique'] ?? ''));

// Jeux offerts (séries Jeux vidéo) : badge CD en bas à droite.
$seriesForCategories = is_array($series) && $series !== []
    ? $series
    : ['categories' => (string) ($row['series_categories'] ?? '')];
$hasJeuxOfferts = Moncine\MagazineSeriesCategory::includesJeuxVideo($seriesForCategories)
    && (int) ($row['jeux_offerts_count'] ?? 0) > 0;

$coverAriaLabel = 'Numéro ' . $numeroLabel;
if ($hasPaper) {
    $coverAriaLabel .= ' — possédé en papier';
}
if ($hasJeuxOfferts) {
    $coverAriaLabel .= ' — jeu(x) offert(s)';
}
?>
<article class="<?= Moncine\View::escape($cardClass) ?>">
    <a href="<?= Moncine\View::escape($issueUrl) ?>" class="magazine-issue-card__cover-link"
       aria-label="<?= Moncine\View::escape($coverAriaLabel) ?>">
        <?php if ($cover !== ''): ?>
            <img src="<?= $cover ?>" alt="" class="magazine-cover magazine-cover--card" loading="lazy">
        <?php else: ?>
            <span class="magazine-cover magazine-cover--card magazine-cover--empty" aria-hidden="true"></span>
        <?php endif; ?>
        <?php if ($hasPaper): ?>
            <!-- Feuille papier avec lignes d’écriture — bien lisible sur la jaquette. -->
            <span class="magazine-issue-card__paper-badge" title="Possédé en papier" aria-hidden="true">
                <svg class="magazine-issue-card__paper-icon" viewBox="0 0 32 32" focusable="false">
                    <!-- Fond de feuille -->
                    <path class="magazine-issue-card__paper-sheet"
                          d="M7 3h12l6 6v18a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                    <!-- Coin plié -->
                    <path class="magazine-issue-card__paper-fold"
                          d="M19 3v5a1 1 0 0 0 1 1h5l-6-6z"/>
                    <!-- Lignes d’écriture -->
                    <path class="magazine-issue-card__paper-lines"
                          d="M9.5 14h13v2.2h-13zm0 4.5h13v2.2h-13zm0 4.5h9v2.2h-9z"/>
                </svg>
            </span>
        <?php endif; ?>
        <?php if ($hasJeuxOfferts): ?>
            <!-- Même icône CD/DVD que sur les fiches jeux. -->
            <span class="magazine-issue-card__cd-badge" title="Jeu(x) offert(s)" aria-hidden="true">
                <?php
                $iconKey = Moncine\GameEditionIcons::CD_DVD;
                require MONCINE_ROOT . '/templates/_game_edition_icon.php';
                ?>
            </span>
        <?php endif; ?>
    </a>
    <?php if ($showFooter): ?>
    <div class="magazine-issue-card__footer">
        <a href="<?= Moncine\View::escape($issueUrl) ?>" class="magazine-issue-card__num">
            <?php if (!empty($row['est_hors_serie'])): ?>HS <?php endif; ?>N° <?= Moncine\View::escape((string) ($row['numero'] ?? '')) ?>
        </a>
        <?php if ($pdfUrl !== ''): ?>
            <a href="<?= Moncine\View::escape($pdfUrl) ?>"
               class="btn btn-accent btn-sm magazine-issue-card__pdf"
               target="_blank"
               rel="noopener">PDF</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="collection-grid__hover-bubble" aria-hidden="true">
        <?php
        $showSeriesTitle = $showSeriesTitleInBubble;
        require MONCINE_ROOT . '/templates/_magazine_issue_grid_caption.php';
        ?>
    </div>
</article>
