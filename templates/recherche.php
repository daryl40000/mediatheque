<?php
/**
 * @var string $query
 * @var list<array<string, mixed>> $libraryResults
 * @var list<array<string, mixed>> $catalogResults
 */
$query = trim((string) ($query ?? ''));
$libraryResults = $libraryResults ?? [];
$catalogResults = $catalogResults ?? [];
$hasQuery = mb_strlen($query) >= 2;
$totalCount = count($libraryResults) + count($catalogResults);
?>
<section class="global-search-page">
    <h1>Recherche</h1>
    <p class="lead">Trouvez un titre dans votre bibliothèque ou dans le catalogue partagé.</p>

    <form method="get" action="/recherche.php" class="global-search-page__form" role="search">
        <label for="global-search-page-input" class="visually-hidden">Rechercher</label>
        <input type="search" id="global-search-page-input" name="q"
               value="<?= Moncine\View::escape($query) ?>"
               placeholder="Titre, réalisateur, studio, saga…" autocomplete="off" maxlength="120">
        <button type="submit" class="btn btn-primary">Rechercher</button>
    </form>

    <?php if ($query !== '' && !$hasQuery): ?>
        <p class="hint">Saisissez au moins 2 caractères.</p>
    <?php elseif ($hasQuery && $totalCount === 0): ?>
        <p class="hint">Aucun résultat pour « <?= Moncine\View::escape($query) ?> ».</p>
    <?php elseif ($hasQuery): ?>
        <?php if ($libraryResults !== []): ?>
            <section class="global-search-page__section" aria-labelledby="global-search-library-heading">
                <h2 id="global-search-library-heading" class="global-search-page__section-title">
                    Ma bibliothèque <span class="hint">(<?= count($libraryResults) ?>)</span>
                </h2>
                <ul class="global-search-results" role="list">
                    <?php foreach ($libraryResults as $row): ?>
                        <?php require MONCINE_ROOT . '/templates/_global_search_result_item.php'; ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($catalogResults !== []): ?>
            <section class="global-search-page__section" aria-labelledby="global-search-catalog-heading">
                <h2 id="global-search-catalog-heading" class="global-search-page__section-title">
                    Catalogue <span class="hint">(<?= count($catalogResults) ?>)</span>
                </h2>
                <ul class="global-search-results" role="list">
                    <?php foreach ($catalogResults as $row): ?>
                        <?php require MONCINE_ROOT . '/templates/_global_search_result_item.php'; ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>
