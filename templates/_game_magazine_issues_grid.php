<?php
/**
 * Grille des magazines liés à un jeu ou un film.
 * Couverture + titre de revue bien visibles (pas seulement au survol).
 *
 * @var list<array<string, mixed>> $magazineCoverageRows
 */
$magazineCoverageRows = $magazineCoverageRows ?? [];
if ($magazineCoverageRows === []) {
    return;
}
?>
<div class="magazine-issues-grid magazine-issues-grid--media-coverage" role="list">
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
        $seriesTitre = trim((string) ($row['series_titre'] ?? ''));
        $numero = trim((string) ($row['numero'] ?? ''));
        $isHorsSerie = !empty($row['est_hors_serie']);
        $numeroLabel = '';
        if ($numero !== '') {
            $numeroLabel = ($isHorsSerie ? 'HS ' : 'n°') . $numero;
        } elseif ($isHorsSerie) {
            $numeroLabel = 'Hors-série';
        }
        $issueTitle = $seriesTitre;
        if ($numeroLabel !== '') {
            $issueTitle = trim($issueTitle . ($issueTitle !== '' ? ' — ' : '') . $numeroLabel);
        }
        $categoryLabels = array_values(array_filter(
            is_array($row['category_labels'] ?? null) ? $row['category_labels'] : [],
            static fn (mixed $label): bool => trim((string) $label) !== ''
        ));
        if ($categoryLabels === [] && trim((string) ($row['category_label'] ?? '')) !== '') {
            $categoryLabels = [(string) $row['category_label']];
        }
        $inLibrary = !empty($row['in_library']);
        $dateLabel = trim((string) ($row['date_label'] ?? ''));
        $pdfUrl = trim((string) ($row['pdf_url'] ?? ''));
        $articlePage = Moncine\MagazineSubjectRepository::normalizePage($row['article_page'] ?? 0);
        $scoreDisplay = trim((string) ($row['score_display'] ?? ''));
        $scoreStars = is_array($row['score_stars'] ?? null) ? $row['score_stars'] : [];
        $scorePercent = $row['score_percent'] ?? null;
        $cardClass = 'magazine-issue-card magazine-issue-card--media-coverage';
        if (!$inLibrary) {
            $cardClass .= ' magazine-issue-card--unowned';
        }
        ?>
        <article class="<?= Moncine\View::escape($cardClass) ?>" role="listitem">
            <?php if ($navUrl !== ''): ?>
                <a href="<?= Moncine\View::escape($navUrl) ?>"
                   class="magazine-issue-card__cover-link"
                   title="<?= Moncine\View::escape($issueTitle) ?>"
                   aria-label="<?= Moncine\View::escape($issueTitle) ?>">
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

            <div class="magazine-issue-card__body magazine-issue-card__body--media-coverage">
                <h3 class="magazine-issue-card__title magazine-issue-card__title--media-coverage">
                    <?php if ($navUrl !== ''): ?>
                        <a href="<?= Moncine\View::escape($navUrl) ?>" class="magazine-issue-card__title-link">
                    <?php endif; ?>
                        <?php if ($seriesTitre !== ''): ?>
                            <span class="magazine-issue-card__series"><?= Moncine\View::escape($seriesTitre) ?></span>
                        <?php endif; ?>
                        <?php if ($numeroLabel !== ''): ?>
                            <span class="magazine-issue-card__numero"><?= Moncine\View::escape($numeroLabel) ?></span>
                        <?php endif; ?>
                        <?php if ($seriesTitre === '' && $numeroLabel === ''): ?>
                            <span class="magazine-issue-card__series">Magazine</span>
                        <?php endif; ?>
                    <?php if ($navUrl !== ''): ?>
                        </a>
                    <?php endif; ?>
                </h3>

                <?php if ($dateLabel !== ''): ?>
                    <p class="magazine-issue-card__meta hint"><?= Moncine\View::escape($dateLabel) ?></p>
                <?php endif; ?>

                <?php if ($categoryLabels !== []): ?>
                    <div class="magazine-issue-card__tags">
                        <?php foreach ($categoryLabels as $categoryLabel): ?>
                            <span class="magazine-tag magazine-tag--subject"><?= Moncine\View::escape((string) $categoryLabel) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($pdfUrl !== '' || $scoreDisplay !== ''): ?>
                    <div class="magazine-issue-card__footer magazine-issue-card__footer--coverage">
                        <?php if ($scoreDisplay !== ''): ?>
                            <?php if ($scoreStars !== []): ?>
                                <span class="magazine-issue-card__score magazine-issue-card__score--stars"
                                      title="<?= Moncine\View::escape($scoreDisplay
                                          . ($scorePercent !== null
                                              ? ' · ≈ ' . Moncine\MagazineRatingScale::formatNumber((float) $scorePercent) . '/100'
                                              : '')) ?>">
                                    <?php foreach ($scoreStars as $starPart): ?>
                                        <span class="magazine-subject-strip__star magazine-subject-strip__star--<?= Moncine\View::escape((string) $starPart) ?>"
                                              aria-hidden="true"></span>
                                    <?php endforeach; ?>
                                    <span class="visually-hidden"><?= Moncine\View::escape($scoreDisplay) ?></span>
                                </span>
                            <?php else: ?>
                                <span class="magazine-issue-card__score"
                                      title="<?= $scorePercent !== null
                                          ? '≈ ' . Moncine\View::escape(Moncine\MagazineRatingScale::formatNumber((float) $scorePercent)) . '/100'
                                          : Moncine\View::escape($scoreDisplay) ?>">
                                    <?= Moncine\View::escape($scoreDisplay) ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($pdfUrl !== ''): ?>
                            <a href="<?= Moncine\View::escape($pdfUrl) ?>"
                               class="btn btn-accent btn-sm magazine-issue-card__pdf"
                               target="_blank"
                               rel="noopener"
                               title="<?= $articlePage > 0
                                   ? 'Ouvrir le PDF à la page ' . $articlePage
                                   : 'Ouvrir le PDF' ?>">
                                PDF<?= $articlePage > 0 ? ' p.' . $articlePage : '' ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
