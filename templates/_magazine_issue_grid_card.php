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
?>
<article class="<?= Moncine\View::escape($cardClass) ?>">
    <a href="<?= Moncine\View::escape($issueUrl) ?>" class="magazine-issue-card__cover-link"
       aria-label="Numéro <?= Moncine\View::escape($numeroLabel) ?>">
        <?php if ($cover !== ''): ?>
            <img src="<?= $cover ?>" alt="" class="magazine-cover magazine-cover--card" loading="lazy">
        <?php else: ?>
            <span class="magazine-cover magazine-cover--card magazine-cover--empty" aria-hidden="true"></span>
        <?php endif; ?>
    </a>
    <?php if ($showFooter): ?>
    <div class="magazine-issue-card__footer">
        <a href="<?= Moncine\View::escape($issueUrl) ?>" class="magazine-issue-card__num">
            N° <?= Moncine\View::escape((string) ($row['numero'] ?? '')) ?>
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
