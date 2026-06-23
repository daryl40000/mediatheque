<?php
/**
 * Liste partagée visiteur — jeux, vue tableau.
 *
 * @var list<array<string, mixed>> $games
 * @var callable $shareSortHeader
 * @var string $rawToken
 * @var string $sortBy
 * @var string $sortDir
 * @var string $query
 * @var string $viewMode
 */
$listContext = Moncine\ShareLinkService::collectionQueryParams(
    $query ?? '',
    $sortBy ?? 'titre',
    $sortDir ?? 'asc',
    '',
    $viewMode ?? ''
);
$mediaDomain = Moncine\MediaDomain::JEU;
?>
<p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
<div class="table-scroll">
<table class="films-table films-table--sortable films-table--wide">
    <thead>
        <tr>
            <th class="col-poster" scope="col">Jaquette</th>
            <?php $shareSortHeader('Titre', 'titre'); ?>
            <th scope="col">Plateforme</th>
            <?php $shareSortHeader('Année', 'annee'); ?>
            <?php $shareSortHeader('Studio', 'studio'); ?>
            <?php $shareSortHeader('Genres', 'genre'); ?>
            <?php $shareSortHeader('Support', 'support'); ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($games as $game):
            $gameId = (int) ($game['id'] ?? 0);
            $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
            $gameUrl = Moncine\ShareLinkService::gameUrl($rawToken, $gameId, $listContext);
            $displayTitle = (string) ($game['display_titre'] ?? $game['titre'] ?? '');
            $genreList = $game['genre_list'] ?? Moncine\GameGenre::parseList((string) ($game['genre'] ?? ''));
            ?>
            <tr>
                <td class="col-poster">
                    <a href="<?= Moncine\View::escape($gameUrl) ?>" class="films-table__poster-link"
                       title="Voir la fiche : <?= Moncine\View::escape($displayTitle) ?>">
                        <?php if ($posterSrc !== ''): ?>
                            <img class="films-table__poster" src="<?= $posterSrc ?>"
                                 alt="Jaquette de <?= Moncine\View::escape($displayTitle) ?>"
                                 width="44" height="66" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="films-table__poster films-table__poster--empty" aria-hidden="true"></span>
                        <?php endif; ?>
                    </a>
                </td>
                <td>
                    <a href="<?= Moncine\View::escape($gameUrl) ?>" class="film-link">
                        <?= Moncine\View::escape($displayTitle) ?>
                    </a>
                </td>
                <td><?= Moncine\View::escape((string) ($game['platform_short'] ?? '')) ?></td>
                <td><?= (int) ($game['annee'] ?? 0) > 0 ? (int) $game['annee'] : '—' ?></td>
                <td><?= Moncine\View::escape((string) ($game['studio'] ?? '')) ?></td>
                <td>
                    <?php if ($genreList === []): ?>
                        —
                    <?php else: ?>
                        <?= Moncine\View::escape(implode(', ', $genreList)) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $iconKeys = $game['edition_icon_keys'] ?? Moncine\GameEditionIcons::iconKeys($game);
                    $supplementalText = Moncine\GameEditionIcons::supplementalText($game);
                    require MONCINE_ROOT . '/templates/_game_edition_icons.php';
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
