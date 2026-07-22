<?php
/**
 * Sujets / tests associés à un numéro magazine.
 *
 * @var list<array<string, mixed>> $issueSubjects
 * @var array<string, string> $subjectCategories
 * @var bool $subjectsAvailable
 * @var array<string, mixed> $issue
 * @var bool $subjectSaved
 * @var bool $subjectDetached
 * @var string $subjectError
 * @var list<string> $seriesTags
 * @var string|null $forcedTag
 * @var int $parutionYear
 * @var int $defaultSubjectYear
 * @var list<int> $subjectYearChoices
 * @var bool $catalogMediaLinkAvailable
 * @var array<string, string> $catalogMediaDomainChoices
 */
$bibId = (int) ($issue['bib_id'] ?? 0);
$subjectDetached = $subjectDetached ?? false;
$catalogMediaLinkAvailable = $catalogMediaLinkAvailable ?? false;
$catalogMediaDomainChoices = $catalogMediaDomainChoices ?? MagazineSubjectCatalogLink::linkableMediaDomainChoices();
$hasMultipleTags = count($seriesTags) > 1;
$hasSingleTag = $forcedTag !== null;
$defaultSubjectYear = (int) ($defaultSubjectYear ?? Moncine\MagazineSubject::defaultSubjectYearFromIssue($issue));
$subjectYearChoices = $subjectYearChoices ?? Moncine\MagazineSubject::subjectYearChoices($defaultSubjectYear);

// Texte d’aide regroupé dans la bulle « i » à côté du titre.
$subjectsInfoParts = [
    'Associez un sujet testé ou traité dans ce numéro (jeu, voiture, matériel, dossier, interview…).',
];
if ($parutionYear > 0) {
    $subjectsInfoParts[] = 'Choisissez l’année affichée sur le tag (par défaut celle du numéro : '
        . (int) $parutionYear . ').';
} else {
    $subjectsInfoParts[] = 'Choisissez l’année affichée sur le tag '
        . '(par défaut l’année courante si le numéro n’a pas encore de date de parution).';
}
if ($hasSingleTag) {
    $subjectsInfoParts[] = 'Tag de la série : ' . (string) $forcedTag
        . ' — ajouté automatiquement à chaque sujet de cette revue.';
} elseif ($hasMultipleTags) {
    $subjectsInfoParts[] = 'Cette revue a plusieurs tags — choisissez celui qui correspond à chaque sujet.';
}
?>
<section class="magazine-subjects-section" aria-label="Sujets et tests">
    <?php
    $title = 'Sujets et tests';
    $tag = 'h2';
    $class = '';
    $info = implode(' ', $subjectsInfoParts);
    $infoHtml = null;
    $infoAria = 'Aide sur les sujets et tests';
    require MONCINE_ROOT . '/templates/_heading_with_info.php';
    ?>

    <?php if ($subjectSaved): ?>
        <p class="alert alert-success">Sujet enregistré.</p>
    <?php endif; ?>
    <?php if ($subjectDetached): ?>
        <p class="alert alert-success">Sujet retiré de ce numéro.</p>
    <?php endif; ?>
    <?php if ($subjectError !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($subjectError) ?></p>
    <?php endif; ?>

    <?php if (!$subjectsAvailable): ?>
        <p class="hint">Module sujets non disponible — rechargez la page dans quelques secondes.</p>
    <?php else: ?>
        <?php if ($issueSubjects === []): ?>
            <p class="hint">Aucun sujet associé à ce numéro.</p>
        <?php else: ?>
            <?php
            $stripSubjects = $issueSubjects;
            require MONCINE_ROOT . '/templates/_magazine_issue_subjects_strip.php';
            unset($stripSubjects);
            ?>
        <?php endif; ?>

        <form method="post" action="/traiter-sujets-numero-magazine.php" class="magazine-subject-form import-form"
              <?php if ($catalogMediaLinkAvailable): ?>
              data-catalog-search-url="<?= Moncine\View::escape(Moncine\View::magazineSubjectCatalogApiUrl()) ?>"
              data-catalog-link-categories="<?= Moncine\View::escape(implode(',', Moncine\MagazineSubject::catalogLinkCategories())) ?>"
              <?php endif; ?>>
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="bib_id" value="<?= $bibId ?>">
            <input type="hidden" name="action" value="attach">
            <?php if ($catalogMediaLinkAvailable): ?>
                <input type="hidden" name="catalog_oeuvre_id" id="attach_catalog_oeuvre_id" value="">
            <?php endif; ?>

            <label for="attach_category">Catégorie</label>
            <select name="category" id="attach_category" required>
                <?php foreach ($subjectCategories as $catKey => $catLabel): ?>
                    <option value="<?= Moncine\View::escape($catKey) ?>"><?= Moncine\View::escape($catLabel) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($catalogMediaLinkAvailable): ?>
                <label for="attach_catalog_media_domain">Média lié (catalogue)</label>
                <select name="catalog_media_domain" id="attach_catalog_media_domain">
                    <?php foreach ($catalogMediaDomainChoices as $domainKey => $domainLabel): ?>
                        <option value="<?= Moncine\View::escape($domainKey) ?>"><?= Moncine\View::escape($domainLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint" id="attach_catalog_media_help">
                    Pour un <strong>test</strong>, une <strong>preview</strong>, un <strong>dossier</strong>,
                    une <strong>soluce</strong>, une <strong>interview</strong>
                    ou un <strong>jeu offert</strong>,
                    choisissez le type de média puis son titre. S’il n’existe pas encore au catalogue,
                    sa fiche sera créée automatiquement.
                </p>
            <?php endif; ?>

            <label for="attach_label">Nom du sujet</label>
            <?php if ($catalogMediaLinkAvailable): ?>
                <p class="hint magazine-subject-form__game-hint" id="attach_game_catalog_hint" hidden>
                    Lié au catalogue : <strong id="attach_game_catalog_label"></strong>
                    <button type="button" class="btn btn-ghost btn-sm" id="attach_clear_game_catalog">Effacer le lien</button>
                </p>
            <?php endif; ?>
            <div class="magazine-subject-form__label-wrap magazine-subject-search__row--autocomplete"
                 data-magazine-subject-autocomplete="fill"
                 data-search-url="<?= Moncine\View::escape(Moncine\View::magazineSubjectApiUrl()) ?>">
                <input type="text" name="label" id="attach_label" required maxlength="200"
                       placeholder="Ex. Gran Turismo 7, Inception, Peugeot 308 III"
                       autocomplete="off" autocapitalize="off" spellcheck="false"
                       aria-autocomplete="list" aria-controls="attach_label_suggestions"
                       aria-describedby="<?= $catalogMediaLinkAvailable ? 'attach_catalog_media_help' : '' ?>">
                <ul class="catalog-title-autocomplete__list magazine-subject-suggestions" id="attach_label_suggestions"
                    role="listbox" hidden></ul>
            </div>

            <label for="attach_parution_year">Année (sur le tag)</label>
            <select name="parution_year" id="attach_parution_year" required>
                <?php foreach ($subjectYearChoices as $yearChoice): ?>
                    <option value="<?= (int) $yearChoice ?>"<?= (int) $yearChoice === $defaultSubjectYear ? ' selected' : '' ?>>
                        <?= (int) $yearChoice ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="hint">
                Utile si le test porte sur une version antérieure ou postérieure à la parution du numéro.
            </p>

            <?php if ($hasSingleTag): ?>
                <p class="hint">
                    Tag : <strong><?= Moncine\View::escape($forcedTag) ?></strong>
                </p>
            <?php elseif ($hasMultipleTags): ?>
                <label for="attach_detail">Tag</label>
                <select name="detail" id="attach_detail" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($seriesTags as $tag): ?>
                        <option value="<?= Moncine\View::escape($tag) ?>">
                            <?= Moncine\View::escape($tag) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <label for="attach_detail">Précision (optionnel)</label>
                <input type="text" name="detail" id="attach_detail" maxlength="120"
                       placeholder="Variante, finition, motorisation…">
                <p class="hint">
                    Vous pouvez aussi définir des tags sur la <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl((int) ($issue['series_id'] ?? 0))) ?>">fiche série</a>
                    pour les appliquer automatiquement.
                </p>
            <?php endif; ?>

            <button type="submit" class="btn btn-accent">Ajouter le sujet</button>
        </form>

        <p class="hint">
            <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectSearchUrl()) ?>">Rechercher un sujet</a>
            dans toute votre bibliothèque magazines.
        </p>
    <?php endif; ?>
</section>
