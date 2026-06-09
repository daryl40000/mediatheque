<?php
/**
 * Sujets / tests associés à un numéro magazine.
 *
 * @var list<array<string, mixed>> $issueSubjects
 * @var array<string, string> $subjectCategories
 * @var bool $subjectsAvailable
 * @var array<string, mixed> $issue
 * @var bool $subjectSaved
 * @var string $subjectError
 * @var list<string> $seriesTags
 * @var string|null $forcedTag
 * @var int $parutionYear
 * @var int $defaultSubjectYear
 * @var list<int> $subjectYearChoices
 * @var bool $gameCatalogLinkAvailable
 */
$bibId = (int) ($issue['bib_id'] ?? 0);
$gameCatalogLinkAvailable = $gameCatalogLinkAvailable ?? false;
$hasMultipleTags = count($seriesTags) > 1;
$hasSingleTag = $forcedTag !== null;
$defaultSubjectYear = (int) ($defaultSubjectYear ?? Moncine\MagazineSubject::defaultSubjectYearFromIssue($issue));
$subjectYearChoices = $subjectYearChoices ?? Moncine\MagazineSubject::subjectYearChoices($defaultSubjectYear);
?>
<section class="magazine-subjects-section" aria-labelledby="magazine-subjects-heading">
    <h2 id="magazine-subjects-heading">Sujets et tests</h2>
    <p class="hint">
        Associez un sujet testé ou traité dans ce numéro (jeu, voiture, matériel, dossier, interview…).
        Choisissez l’<strong>année</strong> affichée sur le tag (par défaut celle du numéro
        <?php if ($parutionYear > 0): ?>
            : <strong><?= (int) $parutionYear ?></strong>
        <?php else: ?>
            — année courante si le numéro n’a pas encore de date de parution
        <?php endif; ?>).
    </p>
    <?php if ($hasSingleTag): ?>
        <p class="hint">
            Tag de la série : <strong><?= Moncine\View::escape($forcedTag) ?></strong>
            — ajouté automatiquement à chaque sujet de cette revue.
        </p>
    <?php elseif ($hasMultipleTags): ?>
        <p class="hint">
            Cette revue a plusieurs tags — choisissez celui qui correspond à chaque sujet.
        </p>
    <?php endif; ?>

    <?php if ($subjectSaved): ?>
        <p class="alert alert-success">Sujet enregistré.</p>
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
            <ul class="magazine-subject-tags" role="list">
                <?php foreach ($issueSubjects as $subject): ?>
                    <?php $subjectId = (int) ($subject['id'] ?? 0); ?>
                    <li class="magazine-subject-tags__item" role="listitem">
                        <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectUrl($subjectId)) ?>"
                           class="magazine-subject-tags__link">
                            <span class="magazine-tag magazine-tag--subject">
                                <?= Moncine\View::escape((string) ($subject['category_label'] ?? '')) ?>
                            </span>
                            <?= Moncine\View::escape((string) ($subject['display_label'] ?? '')) ?>
                        </a>
                        <?php if (!empty($subject['catalog_game_url'])): ?>
                            <a href="<?= Moncine\View::escape((string) $subject['catalog_game_url']) ?>"
                               class="magazine-subject-tags__game-link hint"
                               title="Voir la fiche jeu liée">↗ Jeu</a>
                        <?php elseif (!empty($subject['catalog_game'])): ?>
                            <span class="hint" title="Jeu catalogue (pas dans votre bibliothèque jeux)">
                                · <?= Moncine\View::escape((string) ($subject['catalog_game']['display_label'] ?? '')) ?>
                            </span>
                        <?php endif; ?>
                        <form method="post" action="/traiter-sujets-numero-magazine.php" class="inline-form">
                            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                            <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                            <input type="hidden" name="action" value="detach">
                            <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" title="Retirer ce sujet">Retirer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="/traiter-sujets-numero-magazine.php" class="magazine-subject-form import-form"
              <?php if ($gameCatalogLinkAvailable): ?>
              data-game-catalog-url="<?= Moncine\View::escape(Moncine\View::gameCatalogApiUrl()) ?>"
              <?php endif; ?>>
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="bib_id" value="<?= $bibId ?>">
            <input type="hidden" name="action" value="attach">
            <?php if ($gameCatalogLinkAvailable): ?>
                <input type="hidden" name="catalog_oeuvre_id" id="attach_catalog_oeuvre_id" value="">
            <?php endif; ?>

            <label for="attach_category">Catégorie</label>
            <select name="category" id="attach_category" required>
                <?php foreach ($subjectCategories as $catKey => $catLabel): ?>
                    <option value="<?= Moncine\View::escape($catKey) ?>"><?= Moncine\View::escape($catLabel) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="attach_label">Nom du sujet</label>
            <?php if ($gameCatalogLinkAvailable): ?>
                <p class="hint magazine-subject-form__game-hint" id="attach_game_catalog_hint" hidden>
                    Lié au jeu du catalogue : <strong id="attach_game_catalog_label"></strong>
                    <button type="button" class="btn btn-ghost btn-sm" id="attach_clear_game_catalog">Effacer le lien</button>
                </p>
            <?php endif; ?>
            <div class="magazine-subject-form__label-wrap magazine-subject-search__row--autocomplete"
                 data-magazine-subject-autocomplete="fill"
                 data-search-url="<?= Moncine\View::escape(Moncine\View::magazineSubjectApiUrl()) ?>">
                <input type="text" name="label" id="attach_label" required maxlength="200"
                       placeholder="Ex. Gran Turismo 7, Peugeot 308 III, RTX 4080"
                       autocomplete="off" autocapitalize="off" spellcheck="false"
                       aria-autocomplete="list" aria-controls="attach_label_suggestions"
                       aria-describedby="<?= $gameCatalogLinkAvailable ? 'attach_label_game_help' : '' ?>">
                <?php if ($gameCatalogLinkAvailable): ?>
                    <p class="hint" id="attach_label_game_help">
                        Pour un <strong>test</strong>, une <strong>preview</strong> ou une <strong>interview</strong>,
                        choisissez un jeu dans le catalogue pour remplir titre, plateforme et année automatiquement.
                    </p>
                <?php endif; ?>
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
