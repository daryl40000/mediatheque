<?php
/**
 * Grille de couvertures magazine liées à un jeu, avec tags sous chaque vignette.
 *
 * @var list<array<string, mixed>> $magazineCoverageRows
 */
$magazineCoverageRows = $magazineCoverageRows ?? [];
if ($magazineCoverageRows === []) {
    return;
}
?>
<div class="magazine-issues-grid magazine-issues-grid--game-coverage" role="list">
    <?php foreach ($magazineCoverageRows as $row): ?>
        <?php
        $navUrl = trim((string) ($row['issue_nav_url'] ?? ''));
        $posterSrc = trim((string) ($row['poster_src'] ?? ''));
        if ($posterSrc === '') {
            $poster = trim((string) ($row['poster_url'] ?? ''));
            if ($poster === '') {
                $poster = trim((string) ($row['series_poster_url'] ?? ''));
            }
            $posterSrc = Moncine\View::posterSrc($poster !== '' ? $poster : null);
        }
        $seriesTitre = (string) ($row['series_titre'] ?? '');
        $numero = (string) ($row['numero'] ?? '');
        $issueTitle = $seriesTitre;
        if ($numero !== '') {
            $issueTitle = trim($issueTitle . ($issueTitle !== '' ? ' — ' : '') . 'n°' . $numero);
        }
        $categoryLabels = array_values(array_filter(
            is_array($row['category_labels'] ?? null) ? $row['category_labels'] : [],
            static fn (mixed $label): bool => trim((string) $label) !== ''
        ));
        if ($categoryLabels === [] && trim((string) ($row['category_label'] ?? '')) !== '') {
            $categoryLabels = [(string) $row['category_label']];
        }
        $inLibrary = !empty($row['in_library']);
        // Date de parution déjà formatée (ex. « juin 2024 ») côté MagazineGameLink.
        $dateLabel = trim((string) ($row['date_label'] ?? ''));
        $hoverLabel = $issueTitle;
        if ($dateLabel !== '') {
            $hoverLabel = trim($issueTitle . ($issueTitle !== '' ? ' — ' : '') . $dateLabel);
        }
        ?>
        <article class="magazine-issue-card magazine-issue-card--coverage-tags<?= $inLibrary ? '' : ' magazine-issue-card--unowned' ?>"
                 role="listitem">
            <?php if ($navUrl !== ''): ?>
                <a href="<?= Moncine\View::escape($navUrl) ?>"
                   class="magazine-issue-card__cover-link"
                   title="<?= Moncine\View::escape($hoverLabel) ?>"
                   aria-label="<?= Moncine\View::escape($hoverLabel) ?>">
            <?php else: ?>
                <span class="magazine-issue-card__cover-link magazine-issue-card__cover-link--static">
            <?php endif; ?>
                <?php if ($posterSrc !== ''): ?>
                    <img class="magazine-cover magazine-cover--card"
                         src="<?= $posterSrc ?>"
                         alt=""
                         loading="lazy"
                         decoding="async">
                <?php else: ?>
                    <span class="magazine-cover magazine-cover--card magazine-cover--empty" aria-hidden="true"></span>
                <?php endif; ?>
            <?php if ($navUrl !== ''): ?>
                </a>
            <?php else: ?>
                </span>
            <?php endif; ?>

            <?php if ($categoryLabels !== []): ?>
                <div class="magazine-issue-card__tags">
                    <?php foreach ($categoryLabels as $categoryLabel): ?>
                        <span class="magazine-tag magazine-tag--subject"><?= Moncine\View::escape((string) $categoryLabel) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php // Bulle au survol : mois et année de parution du numéro. ?>
            <?php if ($dateLabel !== ''): ?>
                <div class="collection-grid__hover-bubble" aria-hidden="true">
                    <div class="collection-grid__caption">
                        <?php if ($issueTitle !== ''): ?>
                            <strong class="magazine-issue-card__bubble-title"><?= Moncine\View::escape($issueTitle) ?></strong>
                        <?php endif; ?>
                        <span class="collection-grid__meta"><?= Moncine\View::escape($dateLabel) ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
