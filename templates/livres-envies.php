<?php
/**
 * Mes envies livres.
 *
 * @var list<array<string, mixed>> $books
 * @var string $query
 * @var string $sortBy
 * @var string $sortDir
 * @var int $totalCount
 * @var string $moduleError
 */

$sortBy = $sortBy ?? 'titre';
$sortDir = $sortDir ?? 'asc';
$query = $query ?? '';
$totalCount = (int) ($totalCount ?? count($books ?? []));
$moduleError = $moduleError ?? '';

$sortHeader = static function (string $label, string $column) use ($sortBy, $sortDir, $query): void {
    $active = $sortBy === $column;
    $aria = $active
        ? (strtolower($sortDir) === 'desc' ? 'descending' : 'ascending')
        : 'none';
    ?>
    <th class="<?= $active ? 'sorted' : '' ?>" aria-sort="<?= $aria ?>">
        <a href="<?= Moncine\View::escape(Moncine\View::livresSortUrl($column, $sortBy, $sortDir, $query, true)) ?>">
            <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
        </a>
    </th>
    <?php
};
?>
<section class="collection-page wishlist-page">
    <div class="collection-page__head">
        <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['wishlist']) ?></h1>
        <div class="collection-page__head-actions">
            <a href="<?= Moncine\View::escape(Moncine\View::addLivreUrl(Moncine\LibraryStatut::WISHLIST)) ?>"
               class="btn btn-primary">Ajouter une envie</a>
        </div>
    </div>

    <p class="lead">Livres que vous souhaitez acquérir.</p>

    <nav class="ui-pill-nav" aria-label="Navigation envies livres">
        <a href="/livres.php" class="ui-pill">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a>
        <a href="/statistiques.php" class="ui-pill"><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['stats']) ?></a>
    </nav>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['promoted']) && (string) $_GET['promoted'] === '1'): ?>
        <div class="alert alert-success">Livre ajouté à votre collection.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['promote_error'])): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['promote_error']) ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['deleted']) && (string) $_GET['deleted'] === '1'): ?>
        <div class="alert alert-success">
            Envie retirée<?= !empty($_GET['deleted_title'])
                ? ' : « ' . Moncine\View::escape((string) $_GET['deleted_title']) . ' »'
                : '' ?>.
        </div>
    <?php endif; ?>

    <form method="get" action="/livres-envies.php" class="collection-search import-form">
        <label for="livres_w_q">Rechercher</label>
        <div class="collection-search__row">
            <input type="search" name="q" id="livres_w_q"
                   value="<?= Moncine\View::escape($query) ?>"
                   placeholder="Titre, auteur…"
                   autocomplete="off">
            <?php if ($sortBy !== 'titre'): ?>
                <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
            <?php endif; ?>
            <?php if (strtolower($sortDir) === 'desc'): ?>
                <input type="hidden" name="dir" value="desc">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Rechercher</button>
        </div>
    </form>

    <?php if ($totalCount === 0): ?>
        <p class="hint">Aucune envie pour l’instant.</p>
    <?php else: ?>
        <p class="stats"><?= $totalCount ?> envie<?= $totalCount > 1 ? 's' : '' ?>.</p>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php $sortHeader('Titre', 'titre'); ?>
                        <?php $sortHeader('Auteur', 'auteur'); ?>
                        <?php $sortHeader('Année', 'annee'); ?>
                        <th>Catégories</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <?php $bibId = (int) ($book['bib_id'] ?? 0); ?>
                        <tr>
                            <td>
                                <a href="<?= Moncine\View::escape(Moncine\View::livreUrl($bibId)) ?>">
                                    <?= Moncine\View::escape((string) ($book['display_titre'] ?? $book['titre'] ?? '')) ?>
                                </a>
                            </td>
                            <td><?= Moncine\View::escape((string) ($book['auteur'] ?? '')) ?></td>
                            <td><?= (int) ($book['annee'] ?? 0) > 0 ? (int) $book['annee'] : '—' ?></td>
                            <td>
                                <?php
                                $labelPrefix = '';
                                require MONCINE_ROOT . '/templates/_livre_categories_display.php';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
