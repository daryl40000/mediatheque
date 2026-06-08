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
 */
$bibId = (int) ($issue['bib_id'] ?? 0);
$hasMultipleTags = count($seriesTags) > 1;
$hasSingleTag = $forcedTag !== null;
?>
<section class="magazine-subjects-section" aria-labelledby="magazine-subjects-heading">
    <h2 id="magazine-subjects-heading">Sujets et tests</h2>
    <p class="hint">
        Associez un sujet testé ou traité dans ce numéro (jeu, voiture, matériel, dossier, interview…).
        <?php if ($parutionYear > 0): ?>
            L’année affichée sur le tag est celle du numéro (<strong><?= (int) $parutionYear ?></strong>).
        <?php else: ?>
            Renseignez d’abord la <strong>date de parution</strong> du numéro pour pouvoir ajouter un sujet.
        <?php endif; ?>
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

        <?php if ($parutionYear <= 0): ?>
            <p class="hint">Modifiez le numéro pour ajouter une date de parution, puis revenez ici.</p>
        <?php else: ?>
            <form method="post" action="/traiter-sujets-numero-magazine.php" class="magazine-subject-form import-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                <input type="hidden" name="action" value="attach">

                <label for="attach_category">Catégorie</label>
                <select name="category" id="attach_category" required>
                    <?php foreach ($subjectCategories as $catKey => $catLabel): ?>
                        <option value="<?= Moncine\View::escape($catKey) ?>"><?= Moncine\View::escape($catLabel) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="attach_label">Nom du sujet</label>
                <div class="magazine-subject-form__label-wrap magazine-subject-search__row--autocomplete"
                     data-magazine-subject-autocomplete="fill"
                     data-search-url="<?= Moncine\View::escape(Moncine\View::magazineSubjectApiUrl()) ?>">
                    <input type="text" name="label" id="attach_label" required maxlength="200"
                           placeholder="Ex. Gran Turismo 7, Peugeot 308 III, RTX 4080"
                           autocomplete="off" autocapitalize="off" spellcheck="false"
                           aria-autocomplete="list" aria-controls="attach_label_suggestions">
                    <ul class="catalog-title-autocomplete__list magazine-subject-suggestions" id="attach_label_suggestions"
                        role="listbox" hidden></ul>
                </div>

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
        <?php endif; ?>

        <p class="hint">
            <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectSearchUrl()) ?>">Rechercher un sujet</a>
            dans toute votre bibliothèque magazines.
        </p>
    <?php endif; ?>
</section>
