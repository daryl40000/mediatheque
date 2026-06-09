<?php
/** @var list<array<string, mixed>> $games */
/** @var string $query */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var int $totalCount */
/** @var string $moduleError */

$sortBy = $sortBy ?? 'titre';
$sortDir = $sortDir ?? 'asc';

$sortHeader = static function (string $label, string $column) use ($sortBy, $sortDir, $query): void {
    $active = $sortBy === $column;
    $aria = $active
        ? (strtolower($sortDir) === 'desc' ? 'descending' : 'ascending')
        : 'none';
    ?>
    <th class="<?= $active ? 'sorted' : '' ?>" aria-sort="<?= $aria ?>">
        <a href="<?= Moncine\View::escape(Moncine\View::gamesSortUrl($column, $sortBy, $sortDir, $query)) ?>">
            <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
        </a>
    </th>
    <?php
};
?>
<section class="collection-page">
    <header class="collection-page__header">
        <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></h1>
        <p class="lead">
            Vos jeux vidéo (physiques ou dématérialisés). Les fiches peuvent être reliées aux
            <strong>tests et previews</strong> de vos magazines (PC Jeux, Joystick…).
        </p>
        <div class="collection-page__actions">
            <a href="/ajouter-jeu.php" class="btn btn-accent">Ajouter un jeu</a>
            <a href="/jeux-envies.php" class="btn btn-secondary">Mes envies jeux</a>
        </div>
    </header>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted']) && (string) $_GET['deleted'] === '1'): ?>
        <div class="alert alert-success">
            Jeu retiré<?= !empty($_GET['deleted_title'])
                ? ' : « ' . Moncine\View::escape((string) $_GET['deleted_title']) . ' »'
                : '' ?>.
        </div>
    <?php endif; ?>

    <form method="get" action="/jeux.php" class="collection-search">
        <label for="jeux_q">Rechercher</label>
        <input type="search" name="q" id="jeux_q"
               value="<?= Moncine\View::escape($query) ?>"
               placeholder="Titre, studio, genre…">
        <?php if ($sortBy !== 'titre'): ?>
            <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
        <?php endif; ?>
        <?php if (strtolower($sortDir) === 'desc'): ?>
            <input type="hidden" name="dir" value="desc">
        <?php endif; ?>
        <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
    </form>

    <?php if ($totalCount === 0): ?>
        <p class="hint">Aucun jeu dans votre collection pour l’instant.</p>
    <?php else: ?>
        <p class="hint"><?= (int) $totalCount ?> jeu<?= $totalCount > 1 ? 'x' : '' ?> trouvé<?= $totalCount > 1 ? 's' : '' ?>.</p>
        <p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
        <div class="table-scroll">
            <table class="films-table films-table--sortable films-table--wide">
                <thead>
                    <tr>
                        <th class="col-poster" scope="col">Jaquette</th>
                        <?php $sortHeader('Titre', 'titre'); ?>
                        <th scope="col">Plateforme</th>
                        <?php $sortHeader('Année', 'annee'); ?>
                        <?php $sortHeader('Studio', 'studio'); ?>
                        <?php $sortHeader('Genres', 'genre'); ?>
                        <?php $sortHeader('Support', 'support'); ?>
                        <?php $sortHeader('Note', 'note'); ?>
                        <?php $sortHeader('Ajouté le', 'added_at'); ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games as $game): ?>
                        <?php
                        $bibId = (int) ($game['id'] ?? 0);
                        $gameUrl = Moncine\View::gameUrl($bibId);
                        $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
                        $genreList = $game['genre_list'] ?? Moncine\GameGenre::parseList((string) ($game['genre'] ?? ''));
                        ?>
                        <tr>
                            <td class="col-poster">
                                <a href="<?= Moncine\View::escape($gameUrl) ?>" class="films-table__poster-link"
                                   title="Voir la fiche : <?= Moncine\View::escape((string) ($game['titre'] ?? '')) ?>">
                                    <?php if ($posterSrc !== ''): ?>
                                        <img class="films-table__poster" src="<?= $posterSrc ?>"
                                             alt="Jaquette de <?= Moncine\View::escape((string) ($game['titre'] ?? '')) ?>"
                                             width="44" height="66" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="films-table__poster films-table__poster--empty" aria-hidden="true"></span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td>
                                <?php require MONCINE_ROOT . '/templates/_game_list_title.php'; ?>
                            </td>
                            <td><?= Moncine\View::escape((string) ($game['platform_short'] ?? '')) ?></td>
                            <td><?= (int) ($game['annee'] ?? 0) > 0 ? (int) $game['annee'] : '—' ?></td>
                            <td><?= Moncine\View::escape((string) ($game['studio'] ?? '')) ?></td>
                            <td>
                                <?php if ($genreList === []): ?>
                                    —
                                <?php else: ?>
                                    <?php foreach ($genreList as $genreTag): ?>
                                        <span class="magazine-tag magazine-tag--game-genre"><?= Moncine\View::escape((string) $genreTag) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= Moncine\View::escape((string) ($game['edition_summary'] ?? '')) !== '' ? Moncine\View::escape((string) $game['edition_summary']) : '—' ?></td>
                            <td><?php $film = $game; $showFoyerAverage = true; $layout = 'stacked'; require MONCINE_ROOT . '/templates/_film_ratings.php'; ?></td>
                            <td><?= (string) ($game['added_at_label'] ?? '') !== ''
                                ? Moncine\View::escape((string) $game['added_at_label'])
                                : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
