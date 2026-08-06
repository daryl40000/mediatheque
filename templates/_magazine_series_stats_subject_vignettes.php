<?php
/**
 * Vignettes en lecture seule : sujets d’une série (stats), page PDF cliquable + note des tests.
 *
 * @var list<array<string, mixed>> $filteredSubjects
 */
$filteredSubjects = $filteredSubjects ?? [];
if ($filteredSubjects === []) {
    return;
}
?>
<ul class="magazine-subject-strip__list series-stats-subjects__list" role="list">
    <?php foreach ($filteredSubjects as $subject): ?>
        <?php
        $subjectId = (int) ($subject['id'] ?? 0);
        $navUrl = trim((string) ($subject['media_nav_url'] ?? Moncine\View::magazineSubjectUrl($subjectId)));
        $posterSrc = trim((string) ($subject['media_poster_src'] ?? ''));
        $displayLabel = (string) ($subject['display_label'] ?? '');
        $issueLabel = trim((string) ($subject['issue_label'] ?? ''));
        $issueNavUrl = trim((string) ($subject['issue_nav_url'] ?? ''));
        $inLibrary = !empty($subject['media_in_library']);
        $hasCatalog = !empty($subject['media_has_catalog']);
        $placeholderChar = mb_strtoupper(mb_substr(trim((string) ($subject['label'] ?? $displayLabel)), 0, 1));
        if ($placeholderChar === '') {
            $placeholderChar = '?';
        }
        $itemClass = 'magazine-subject-strip__item';
        if ($hasCatalog && !$inLibrary) {
            $itemClass .= ' magazine-subject-strip__item--catalog-only';
        }

        $articlePage = Moncine\MagazineSubjectRepository::normalizePage($subject['page'] ?? 0);
        $storedObjectId = (int) ($subject['stored_object_id'] ?? 0);
        $pdfUrl = ($storedObjectId > 0 && $articlePage > 0)
            ? Moncine\View::mediaObjectUrl($storedObjectId, $articlePage)
            : '';

        $subjectCategory = Moncine\MagazineSubject::normalizeCategory((string) ($subject['category'] ?? ''));
        $subjectRatingScale = Moncine\MagazineRatingScale::normalize($subject['rating_scale'] ?? null);
        $showTestScore = $subjectRatingScale !== null
            && $subjectCategory === Moncine\MagazineSubject::TEST;
        $testScore = $showTestScore && array_key_exists('score', $subject) && $subject['score'] !== null
            ? (float) $subject['score']
            : null;
        $scoreDisplay = $testScore !== null
            ? Moncine\MagazineRatingScale::formatDisplay($testScore, $subjectRatingScale)
            : '';
        $scoreStars = $testScore !== null
            ? Moncine\MagazineRatingScale::starParts($testScore, $subjectRatingScale)
            : [];
        ?>
        <li class="<?= $itemClass ?>" role="listitem">
            <article class="magazine-subject-strip__card">
                <?php if ($navUrl !== ''): ?>
                    <a href="<?= Moncine\View::escape($navUrl) ?>"
                       class="magazine-subject-strip__link"
                       title="<?= Moncine\View::escape($displayLabel) ?>"
                       aria-label="<?= Moncine\View::escape($displayLabel) ?>">
                <?php else: ?>
                    <span class="magazine-subject-strip__link magazine-subject-strip__link--static"
                          title="<?= Moncine\View::escape($displayLabel) ?>">
                <?php endif; ?>
                    <?php if ($posterSrc !== ''): ?>
                        <img class="magazine-subject-strip__poster"
                             src="<?= $posterSrc ?>"
                             alt=""
                             loading="lazy"
                             width="88"
                             height="132">
                    <?php else: ?>
                        <span class="magazine-subject-strip__placeholder" aria-hidden="true">
                            <?= Moncine\View::escape($placeholderChar) ?>
                        </span>
                    <?php endif; ?>
                <?php if ($navUrl !== ''): ?>
                    </a>
                <?php else: ?>
                    </span>
                <?php endif; ?>

                <div class="magazine-subject-strip__meta">
                    <div class="magazine-subject-strip__meta-view">
                        <?php if ($articlePage > 0): ?>
                            <?php if ($pdfUrl !== ''): ?>
                                <a href="<?= Moncine\View::escape($pdfUrl) ?>"
                                   class="magazine-subject-strip__page-num"
                                   target="_blank"
                                   rel="noopener"
                                   title="Ouvrir le PDF à la page <?= $articlePage ?>">
                                    p.<?= $articlePage ?>
                                </a>
                            <?php else: ?>
                                <span class="magazine-subject-strip__page-num"
                                      title="Page <?= $articlePage ?>">
                                    p.<?= $articlePage ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($showTestScore && $testScore !== null): ?>
                            <?php if ($scoreStars !== []): ?>
                                <span class="magazine-subject-strip__stars"
                                      title="<?= Moncine\View::escape($scoreDisplay) ?>"
                                      aria-label="<?= Moncine\View::escape('Note ' . $scoreDisplay) ?>">
                                    <?php foreach ($scoreStars as $starPart): ?>
                                        <span class="magazine-subject-strip__star magazine-subject-strip__star--<?= Moncine\View::escape($starPart) ?>"
                                              aria-hidden="true"></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php else: ?>
                                <span class="magazine-subject-strip__score-num"
                                      title="<?= Moncine\View::escape($scoreDisplay) ?>">
                                    <?= Moncine\View::escape($scoreDisplay) ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="series-stats-subjects__caption">
                    <span class="series-stats-subjects__title"><?= Moncine\View::escape($displayLabel) ?></span>
                    <?php if ($issueLabel !== ''): ?>
                        <?php if ($issueNavUrl !== ''): ?>
                            <a class="series-stats-subjects__issue"
                               href="<?= Moncine\View::escape($issueNavUrl) ?>">
                                <?= Moncine\View::escape($issueLabel) ?>
                            </a>
                        <?php else: ?>
                            <span class="series-stats-subjects__issue"><?= Moncine\View::escape($issueLabel) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </article>
        </li>
    <?php endforeach; ?>
</ul>
