<?php
/** @var string $query */
/** @var string $category */
/** @var list<array<string, mixed>> $subjects */
/** @var array<string, string> $subjectCategories */
/** @var string $moduleError */
?>
<section class="collection-page">
    <header class="collection-page__header">
        <h1>Recherche par sujet</h1>
        <p class="lead">
            Retrouvez un <strong>test</strong>, une <strong>preview</strong>, un <strong>dossier</strong> ou une <strong>interview</strong>
            dans l’ensemble de vos magazines (tags de série et année du numéro).
        </p>
        <p class="collection-page__actions">
            <a href="/magazines.php" class="btn btn-secondary">← Mes magazines</a>
        </p>
    </header>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php else: ?>
        <form method="get" action="/magazines-recherche.php" class="collection-search magazine-subject-search">
            <div class="magazine-subject-search__row">
                <label for="subject_category">Catégorie</label>
                <select name="category" id="subject_category">
                    <?php foreach ($subjectCategories as $catKey => $catLabel): ?>
                        <option value="<?= Moncine\View::escape($catKey) ?>"<?= $category === $catKey ? ' selected' : '' ?>>
                            <?= Moncine\View::escape($catLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="magazine-subject-search__row magazine-subject-search__row--autocomplete"
                 data-magazine-subject-autocomplete="navigate"
                 data-search-url="<?= Moncine\View::escape(Moncine\View::magazineSubjectApiUrl()) ?>">
                <label for="subject_q">Nom du sujet</label>
                <input type="search" name="q" id="subject_q"
                       value="<?= Moncine\View::escape($query) ?>"
                       placeholder="Ex. Gran Turismo 7, Peugeot 308, RTX 4080…"
                       autocomplete="off" autocapitalize="off" spellcheck="false"
                       aria-autocomplete="list" aria-controls="magazine-subject-suggestions">
                <ul class="catalog-title-autocomplete__list magazine-subject-suggestions" id="magazine-subject-suggestions"
                    role="listbox" hidden></ul>
            </div>
            <button type="submit" class="btn btn-accent">Rechercher</button>
        </form>

        <?php if (!isset($_GET['category']) && $query === ''): ?>
            <div class="alert alert-info">
                <p><strong>Comment ça marche ?</strong></p>
                <ol>
                    <li>Sur la fiche d’un numéro, ajoutez un sujet (ex. « Gran Turismo 7 » avec le tag PS5).</li>
                    <li>Revenez ici pour chercher ce sujet dans <strong>toutes</strong> vos séries.</li>
                    <li>Cliquez sur un résultat pour voir combien de numéros en parlent.</li>
                </ol>
            </div>
        <?php elseif ($subjects === []): ?>
            <p class="hint">Aucun sujet trouvé. Ajoutez-le d’abord sur la fiche d’un numéro, ou élargissez la recherche.</p>
        <?php else: ?>
            <p class="stats"><?= count($subjects) ?> sujet(s) trouvé(s)</p>
            <ul class="magazine-subject-results" role="list">
                <?php foreach ($subjects as $subject): ?>
                    <?php
                    $subjectId = (int) ($subject['id'] ?? 0);
                    $issueCount = (int) ($subject['library_issue_count'] ?? 0);
                    ?>
                    <li class="magazine-subject-results__item" role="listitem">
                        <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectUrl($subjectId)) ?>"
                           class="magazine-subject-results__link">
                            <span class="magazine-tag magazine-tag--subject">
                                <?= Moncine\View::escape((string) ($subject['category_label'] ?? '')) ?>
                            </span>
                            <strong><?= Moncine\View::escape((string) ($subject['display_label'] ?? '')) ?></strong>
                            <span class="hint">
                                <?= $issueCount ?> numéro<?= $issueCount > 1 ? 's' : '' ?> dans votre bibliothèque
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
