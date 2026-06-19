<?php
/**
 * Mes jeux — vue liste (tableau).
 *
 * @var list<array<string, mixed>> $games
 * @var callable $sortHeader
 */
?>
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
                           title="Voir la fiche : <?= Moncine\View::escape((string) ($game['display_titre'] ?? $game['titre'] ?? '')) ?>">
                            <?php if ($posterSrc !== ''): ?>
                                <img class="films-table__poster" src="<?= $posterSrc ?>"
                                     alt="Jaquette de <?= Moncine\View::escape((string) ($game['display_titre'] ?? $game['titre'] ?? '')) ?>"
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
                    <td>
                        <?php
                        $iconKeys = $game['edition_icon_keys'] ?? Moncine\GameEditionIcons::iconKeys($game);
                        $supplementalText = Moncine\GameEditionIcons::supplementalText($game);
                        require MONCINE_ROOT . '/templates/_game_edition_icons.php';
                        ?>
                    </td>
                    <td><?php $film = $game; $showFoyerAverage = true; $layout = 'stacked'; require MONCINE_ROOT . '/templates/_film_ratings.php'; ?></td>
                    <td><?= (string) ($game['added_at_label'] ?? '') !== ''
                        ? Moncine\View::escape((string) $game['added_at_label'])
                        : '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
