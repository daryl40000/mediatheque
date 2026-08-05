<?php
/**
 * Bandeau de vignettes pour les sujets / tests d’un numéro magazine.
 * Par défaut : une rangée par type d’article (label à gauche, vignettes à droite).
 *
 * @var list<array<string, mixed>> $issueSubjects
 * @var list<array<string, mixed>>|null $stripSubjects alias optionnel (prioritaire)
 * @var int $bibId
 * @var bool $stripGroupByCategory regrouper par type (désactiver pour jeux offerts)
 */
$stripSubjects = $stripSubjects ?? $issueSubjects ?? [];
if ($stripSubjects === []) {
    return;
}

$stripGroupByCategory = $stripGroupByCategory ?? true;
$subjectTargetSupplementId = (int) ($subjectTargetSupplementId ?? 0);
$stripRatingScale = Moncine\MagazineRatingScale::normalize($ratingScale ?? null);
$stripScoreMax = Moncine\MagazineRatingScale::maxValue($stripRatingScale);

/**
 * Construit les groupes à afficher : une entrée = une rangée (éventuellement sans label).
 *
 * @param list<array<string, mixed>> $subjects
 * @return list<array{label: string, subjects: list<array<string, mixed>>}>
 */
$buildSubjectGroups = static function (array $subjects, bool $groupByCategory): array {
    if (!$groupByCategory) {
        return [['label' => '', 'subjects' => $subjects]];
    }

    $byCategory = [];
    foreach ($subjects as $subject) {
        $key = Moncine\MagazineSubject::normalizeCategory((string) ($subject['category'] ?? ''));
        if (!isset($byCategory[$key])) {
            $byCategory[$key] = [];
        }
        $byCategory[$key][] = $subject;
    }

    $ordered = [];
    foreach (array_keys(Moncine\MagazineSubject::choices()) as $choiceKey) {
        if (isset($byCategory[$choiceKey])) {
            $ordered[] = [
                'label' => Moncine\MagazineSubject::label($choiceKey),
                'subjects' => $byCategory[$choiceKey],
            ];
            unset($byCategory[$choiceKey]);
        }
    }
    // Catégories inattendues : à la suite, avec leur libellé si possible.
    foreach ($byCategory as $extraKey => $extraSubjects) {
        $ordered[] = [
            'label' => Moncine\MagazineSubject::label($extraKey),
            'subjects' => $extraSubjects,
        ];
    }

    return $ordered;
};

$subjectGroups = $buildSubjectGroups($stripSubjects, $stripGroupByCategory);
?>
<div class="magazine-subject-strip" role="region" aria-label="Sujets associés">
    <?php foreach ($subjectGroups as $group): ?>
        <?php
        $rowLabel = trim((string) ($group['label'] ?? ''));
        $rowSubjects = $group['subjects'] ?? [];
        if ($rowSubjects === []) {
            continue;
        }
        ?>
        <section class="magazine-subject-strip__row<?= $rowLabel === '' ? ' magazine-subject-strip__row--no-label' : '' ?>">
            <?php if ($rowLabel !== ''): ?>
                <?php $rowCount = count($rowSubjects); ?>
                <h3 class="magazine-subject-strip__row-label">
                    <?= Moncine\View::escape($rowLabel) ?>
                    <span class="magazine-subject-strip__row-count"
                          aria-label="<?= $rowCount ?> article<?= $rowCount > 1 ? 's' : '' ?>">
                        (<?= $rowCount ?>)
                    </span>
                </h3>
            <?php endif; ?>
            <ul class="magazine-subject-strip__list" role="list">
                <?php foreach ($rowSubjects as $subject): ?>
                    <?php
                    $subjectId = (int) ($subject['id'] ?? 0);
                    $navUrl = trim((string) ($subject['media_nav_url'] ?? Moncine\View::magazineSubjectUrl($subjectId)));
                    $posterSrc = trim((string) ($subject['media_poster_src'] ?? ''));
                    $displayLabel = (string) ($subject['display_label'] ?? '');
                    $mediaSubtitle = trim((string) ($subject['media_subtitle'] ?? ''));
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

                    // Page PDF déjà connue ou à saisir via le crayon.
                    $articlePage = Moncine\MagazineSubjectRepository::normalizePage($subject['page'] ?? 0);
                    if ($subjectTargetSupplementId > 0) {
                        $storedObjectId = (int) (($supplement['stored_object_id'] ?? 0));
                    } else {
                        $storedObjectId = (int) ($issue['stored_object_id'] ?? 0);
                    }
                    $pdfUrl = ($storedObjectId > 0 && $articlePage > 0)
                        ? Moncine\View::mediaObjectUrl($storedObjectId, $articlePage)
                        : '';
                    $pageEditLabel = $articlePage > 0 ? 'Modifier la page' : 'Indiquer la page';
                    $pageInputId = 'subject_page_' . $subjectId;

                    $subjectCategory = Moncine\MagazineSubject::normalizeCategory((string) ($subject['category'] ?? ''));
                    $showTestScore = $stripRatingScale !== null
                        && $subjectCategory === Moncine\MagazineSubject::TEST;
                    $testScore = $showTestScore && array_key_exists('score', $subject) && $subject['score'] !== null
                        ? (float) $subject['score']
                        : null;
                    $scoreDisplay = $testScore !== null
                        ? Moncine\MagazineRatingScale::formatDisplay($testScore, $stripRatingScale)
                        : '';
                    $scorePercent = $testScore !== null
                        ? Moncine\MagazineRatingScale::toPercent($testScore, $stripRatingScale)
                        : null;
                    $scoreStars = $testScore !== null
                        ? Moncine\MagazineRatingScale::starParts($testScore, $stripRatingScale)
                        : [];
                    $scoreEditLabel = $testScore !== null ? 'Modifier la note' : 'Indiquer la note';
                    $scoreInputId = 'subject_score_' . $subjectId;
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

                            <div class="magazine-subject-strip__meta"
                                 data-subject-meta
                                 onclick="event.stopPropagation()">
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

                                    <button type="button"
                                            class="magazine-subject-strip__meta-edit magazine-subject-strip__meta-edit--page"
                                            data-subject-meta-toggle
                                            title="<?= Moncine\View::escape($showTestScore ? 'Modifier page / note' : $pageEditLabel) ?>"
                                            aria-label="<?= Moncine\View::escape(($showTestScore ? 'Modifier page et note' : $pageEditLabel) . ' pour ' . $displayLabel) ?>"
                                            aria-expanded="false"
                                            aria-controls="<?= Moncine\View::escape($pageInputId) ?>_form">
                                        <span class="magazine-subject-strip__meta-edit-label" aria-hidden="true">p.</span>
                                    </button>
                                    <?php if ($showTestScore): ?>
                                        <button type="button"
                                                class="magazine-subject-strip__meta-edit magazine-subject-strip__meta-edit--score"
                                                data-subject-meta-toggle
                                                title="<?= Moncine\View::escape($scoreEditLabel) ?>"
                                                aria-label="<?= Moncine\View::escape($scoreEditLabel . ' pour ' . $displayLabel) ?>"
                                                aria-expanded="false"
                                                aria-controls="<?= Moncine\View::escape($pageInputId) ?>_form">
                                            <span class="magazine-subject-strip__meta-edit-label" aria-hidden="true">★</span>
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <form method="post"
                                      action="/traiter-sujets-numero-magazine.php"
                                      class="magazine-subject-strip__meta-form"
                                      id="<?= Moncine\View::escape($pageInputId) ?>_form">
                                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                    <input type="hidden" name="bib_id" value="<?= (int) $bibId ?>">
                                    <?php if ($subjectTargetSupplementId > 0): ?>
                                        <input type="hidden" name="supplement_id" value="<?= $subjectTargetSupplementId ?>">
                                    <?php endif; ?>
                                    <input type="hidden" name="action" value="update_meta">
                                    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">

                                    <label class="visually-hidden" for="<?= Moncine\View::escape($pageInputId) ?>">
                                        Page dans le PDF
                                    </label>
                                    <input type="number"
                                           class="magazine-subject-strip__page-input"
                                           name="page"
                                           id="<?= Moncine\View::escape($pageInputId) ?>"
                                           min="0"
                                           max="9999"
                                           step="1"
                                           value="<?= $articlePage > 0 ? $articlePage : '' ?>"
                                           placeholder="page"
                                           inputmode="numeric"
                                           title="Page dans le PDF">

                                    <?php if ($showTestScore): ?>
                                        <label class="visually-hidden" for="<?= Moncine\View::escape($scoreInputId) ?>">
                                            Note du test
                                        </label>
                                        <input type="number"
                                               class="magazine-subject-strip__score-input"
                                               name="score"
                                               id="<?= Moncine\View::escape($scoreInputId) ?>"
                                               min="0"
                                               max="<?= Moncine\View::escape((string) $stripScoreMax) ?>"
                                               step="0.5"
                                               value="<?= $testScore !== null ? Moncine\View::escape(rtrim(rtrim(number_format($testScore, 1, '.', ''), '0'), '.')) : '' ?>"
                                               placeholder="note"
                                               inputmode="decimal"
                                               title="Note (<?= Moncine\View::escape(Moncine\MagazineRatingScale::label($stripRatingScale)) ?>)">
                                    <?php endif; ?>

                                    <button type="submit"
                                            class="btn btn-secondary btn-sm magazine-subject-strip__page-save"
                                            title="<?= $showTestScore ? 'Enregistrer page et note' : 'Enregistrer la page' ?>">OK</button>
                                </form>
                            </div>

                            <form method="post"
                                  action="/traiter-sujets-numero-magazine.php"
                                  class="magazine-subject-strip__detach"
                                  onclick="event.stopPropagation()">
                                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                <input type="hidden" name="bib_id" value="<?= (int) $bibId ?>">
                                <?php if ($subjectTargetSupplementId > 0): ?>
                                    <input type="hidden" name="supplement_id" value="<?= $subjectTargetSupplementId ?>">
                                <?php endif; ?>
                                <input type="hidden" name="action" value="detach">
                                <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                                <button type="submit"
                                        class="magazine-subject-strip__delete"
                                        title="Retirer ce sujet"
                                        aria-label="Retirer <?= Moncine\View::escape($displayLabel) ?>">
                                    <svg class="magazine-subject-strip__delete-icon" viewBox="0 0 24 24"
                                         aria-hidden="true" focusable="false">
                                        <path fill="currentColor"
                                              d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                                    </svg>
                                </button>
                            </form>

                            <div class="magazine-subject-strip__bubble collection-grid__hover-bubble" aria-hidden="true">
                                <div class="collection-grid__caption">
                                    <strong class="magazine-subject-strip__bubble-title"><?= Moncine\View::escape($displayLabel) ?></strong>
                                    <?php if ($scoreDisplay !== ''): ?>
                                        <span class="hint magazine-subject-strip__bubble-meta">
                                            Note <?= Moncine\View::escape($scoreDisplay) ?>
                                            <?php if ($scorePercent !== null): ?>
                                                · ≈ <?= Moncine\View::escape(Moncine\MagazineRatingScale::formatNumber($scorePercent)) ?>/100
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($mediaSubtitle !== ''): ?>
                                        <span class="hint magazine-subject-strip__bubble-meta"><?= Moncine\View::escape($mediaSubtitle) ?></span>
                                    <?php endif; ?>
                                    <?php if ($inLibrary): ?>
                                        <span class="hint magazine-subject-strip__bubble-hint">Dans votre bibliothèque</span>
                                    <?php elseif ($hasCatalog): ?>
                                        <?php
                                        $catalogRow = (new Moncine\MagazineSubjectCatalogLink())->resolveCatalogRow(
                                            (int) ($subject['catalog_oeuvre_id'] ?? 0)
                                        );
                                        $catalogHint = match ((string) ($catalogRow['media_domain'] ?? '')) {
                                            Moncine\MediaDomain::FILM => 'Fiche catalogue film',
                                            Moncine\MediaDomain::JEU => 'Fiche catalogue jeu',
                                            default => 'Fiche catalogue',
                                        };
                                        ?>
                                        <span class="hint magazine-subject-strip__bubble-hint"><?= Moncine\View::escape($catalogHint) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>
<?php
// Évite de laisser le flag actif pour un include suivant.
unset($stripGroupByCategory);
?>
