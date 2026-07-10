<?php
/**
 * Bandeau horizontal de vignettes pour les sujets / tests d’un numéro magazine.
 *
 * @var list<array<string, mixed>> $issueSubjects
 * @var int $bibId
 */
if (($issueSubjects ?? []) === []) {
    return;
}
?>
<div class="magazine-subject-strip" role="region" aria-label="Sujets associés">
    <ul class="magazine-subject-strip__list" role="list">
        <?php foreach ($issueSubjects as $subject): ?>
            <?php
            $subjectId = (int) ($subject['id'] ?? 0);
            $navUrl = trim((string) ($subject['media_nav_url'] ?? Moncine\View::magazineSubjectUrl($subjectId)));
            $posterSrc = trim((string) ($subject['media_poster_src'] ?? ''));
            $displayLabel = (string) ($subject['display_label'] ?? '');
            $categoryLabel = (string) ($subject['category_label'] ?? '');
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

                    <form method="post"
                          action="/traiter-sujets-numero-magazine.php"
                          class="magazine-subject-strip__detach"
                          onclick="event.stopPropagation()">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="bib_id" value="<?= (int) $bibId ?>">
                        <input type="hidden" name="action" value="detach">
                        <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                        <button type="submit"
                                class="magazine-subject-strip__delete"
                                title="Retirer ce sujet de ce numéro"
                                aria-label="Retirer <?= Moncine\View::escape($displayLabel) ?> de ce numéro">
                            <svg class="magazine-subject-strip__delete-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                            </svg>
                        </button>
                    </form>

                    <div class="magazine-subject-strip__bubble collection-grid__hover-bubble" aria-hidden="true">
                        <div class="collection-grid__caption">
                            <?php if ($categoryLabel !== ''): ?>
                                <span class="magazine-tag magazine-tag--subject"><?= Moncine\View::escape($categoryLabel) ?></span>
                            <?php endif; ?>
                            <strong class="magazine-subject-strip__bubble-title"><?= Moncine\View::escape($displayLabel) ?></strong>
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
</div>
