<?php
/** @var string $franchise */
/** @var bool $searched */
/** @var list<array<string, mixed>> $games */
/** @var list<array{franchise: string, game_count: int, poster_url: string}> $franchises */
/** @var list<string> $knownSagas */
/** @var string $moduleError */
/** @var string $viewMode */

$renameError = trim((string) ($_GET['rename_error'] ?? ''));
$renamed = isset($_GET['renamed']) && (string) $_GET['renamed'] === '1';
$renamedCount = isset($_GET['count']) ? (int) $_GET['count'] : 0;
$knownSagas = $knownSagas ?? [];
$viewMode = Moncine\CollectionViewMode::normalize($viewMode ?? '');
$isGridView = Moncine\CollectionViewMode::isGrid($viewMode);
$viewQueryValue = Moncine\CollectionViewMode::queryValue($viewMode);

$sagasViewUrl = static function (?string $mode = null) use ($franchise, $searched, $viewMode): string {
    if ($searched) {
        return Moncine\View::gameFranchiseUrl($franchise, $mode ?? $viewMode);
    }
    $params = [];
    $mode = $mode ?? $viewMode;
    if ($viewParam = Moncine\CollectionViewMode::queryValue($mode)) {
        $params['view'] = $viewParam;
    }

    return $params === [] ? '/sagas-jeux.php' : '/sagas-jeux.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};
?>
<section class="sagas-page">
    <h1><?= $searched ? 'Saga' : 'Sagas jeux' ?></h1>

    <?php if ($moduleError !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></p>
    <?php elseif ($searched): ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape(Moncine\View::gameFranchiseUrl('', $viewMode)) ?>">Toutes les sagas</a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape($franchise) ?></span>
        </p>

        <?php if ($renamed && $renamedCount > 0): ?>
            <p class="alert alert-success">
                Saga renommée : <?= $renamedCount ?> jeu<?= $renamedCount > 1 ? 'x' : '' ?> mis à jour.
            </p>
        <?php endif; ?>
        <?php if ($renameError !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($renameError) ?></p>
        <?php endif; ?>

        <?php if ($games === []): ?>
            <p class="alert alert-warning">
                Aucun jeu trouvé pour cette saga.
                <a href="<?= Moncine\View::escape(Moncine\View::gameFranchiseUrl('', $viewMode)) ?>">Retour à la liste</a>.
            </p>
        <?php else: ?>
            <details class="sagas-rename-panel">
                <summary class="sagas-rename-panel__summary">Renommer cette saga</summary>
                <form method="post" action="/sagas-jeux.php" class="sagas-rename-form import-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="rename_franchise">
                    <input type="hidden" name="franchise_old" value="<?= Moncine\View::escape($franchise) ?>">
                    <?php if ($viewQueryValue !== null): ?>
                        <input type="hidden" name="view" value="<?= Moncine\View::escape($viewQueryValue) ?>">
                    <?php endif; ?>

                    <p class="hint">
                        Corrige le nom pour les jeux de votre collection (ex. faute de frappe ou traduction).
                        Les données proviennent en principe d’IGDB lors de l’enrichissement.
                    </p>

                    <label for="franchise_new_name">Nouveau nom</label>
                    <input type="text" name="franchise_new" id="franchise_new_name" required maxlength="120"
                           value="<?= Moncine\View::escape($franchise) ?>"
                           autocomplete="off"
                           list="game-saga-suggestions">
                    <?php require MONCINE_ROOT . '/templates/_game_saga_datalist.php'; ?>

                    <button type="submit" class="btn btn-primary">Enregistrer le nouveau nom</button>
                </form>
            </details>

            <p class="stats">
                <?= count($games) ?> jeu<?= count($games) > 1 ? 'x' : '' ?>
                dans « <?= Moncine\View::escape($franchise) ?> »
            </p>
            <p class="hint">
                Ordre : année de sortie, puis titre alphabétique.
                <?php if ($isGridView): ?>
                    Cliquez sur une vignette pour ouvrir la fiche.
                <?php else: ?>
                    Cliquez sur une jaquette ou un titre pour ouvrir la fiche.
                <?php endif; ?>
            </p>

            <nav class="ui-pill-bar" aria-label="Mode d’affichage">
                <?php foreach (Moncine\CollectionViewMode::listGridChoices() as $modeKey => $modeLabel): ?>
                    <?php
                    $modeActive = $viewMode === $modeKey;
                    $modeClass = 'ui-pill-bar__item' . ($modeActive ? ' ui-pill--active' : '');
                    ?>
                    <a href="<?= Moncine\View::escape($sagasViewUrl($modeKey)) ?>"
                       class="<?= $modeClass ?>"<?= $modeActive ? ' aria-current="true"' : '' ?>>
                        <?= Moncine\View::escape($modeLabel) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($isGridView): ?>
                <?php require MONCINE_ROOT . '/templates/_saga_games_grid.php'; ?>
            <?php else: ?>
            <p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
            <div class="table-scroll">
            <table class="films-table sagas-detail sagas-detail--games">
                <thead>
                    <tr>
                        <th class="col-poster" scope="col">Jaquette</th>
                        <th>Titre</th>
                        <th>Année</th>
                        <th>Plateforme</th>
                        <th>Studio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games as $game): ?>
                        <?php
                        $bibId = (int) ($game['id'] ?? 0);
                        $gameUrl = Moncine\View::gameUrl($bibId);
                        $displayTitle = (string) ($game['display_titre'] ?? $game['titre'] ?? '');
                        $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
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
                            <td><?= (int) ($game['annee'] ?? 0) > 0 ? (int) $game['annee'] : '—' ?></td>
                            <td><?= Moncine\View::escape((string) ($game['platform_short'] ?? '')) ?></td>
                            <td><?= Moncine\View::escape((string) ($game['studio'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <p class="lead">
            Regroupez vos jeux par <strong>saga</strong> (The Witcher, Grand Theft Auto, Final Fantasy…).
            Le nom est renseigné automatiquement lors de l’<strong>enrichissement IGDB</strong>.
        </p>
        <p class="hint">
            Sur <a href="/jeux.php">Mes jeux</a>, cochez plusieurs titres puis utilisez
            <strong>Ajouter à une saga</strong> pour corriger le nom ou regrouper des jeux.
        </p>

        <?php if ($franchises === []): ?>
            <p class="alert alert-warning">
                Aucune saga pour l’instant. Enrichissez vos jeux via IGDB ou assignez une saga
                manuellement depuis <a href="/jeux.php">Mes jeux</a>.
            </p>
        <?php else: ?>
            <nav class="ui-pill-bar" aria-label="Mode d’affichage">
                <?php foreach (Moncine\CollectionViewMode::listGridChoices() as $modeKey => $modeLabel): ?>
                    <?php
                    $modeActive = $viewMode === $modeKey;
                    $modeClass = 'ui-pill-bar__item' . ($modeActive ? ' ui-pill--active' : '');
                    ?>
                    <a href="<?= Moncine\View::escape($sagasViewUrl($modeKey)) ?>"
                       class="<?= $modeClass ?>"<?= $modeActive ? ' aria-current="true"' : '' ?>>
                        <?= Moncine\View::escape($modeLabel) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($isGridView): ?>
                <?php require MONCINE_ROOT . '/templates/_sagas_franchises_grid.php'; ?>
            <?php else: ?>
            <ul class="sagas-list sagas-list--games">
                <?php foreach ($franchises as $item): ?>
                    <?php
                    $franchiseName = (string) ($item['franchise'] ?? '');
                    $posterSrc = Moncine\View::posterSrc(
                        trim((string) ($item['poster_url'] ?? '')) !== ''
                            ? (string) $item['poster_url']
                            : null
                    );
                    $franchiseUrl = Moncine\View::gameFranchiseUrl($franchiseName, $viewMode);
                    ?>
                    <li>
                        <a href="<?= Moncine\View::escape($franchiseUrl) ?>" class="sagas-list__card">
                            <?php if ($posterSrc !== ''): ?>
                                <img class="sagas-list__poster" src="<?= $posterSrc ?>"
                                     alt="Jaquette du premier jeu de la saga <?= Moncine\View::escape($franchiseName) ?>"
                                     width="52" height="78" loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="sagas-list__poster sagas-list__poster--empty" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="sagas-list__card-body">
                                <span class="sagas-list__link"><?= Moncine\View::escape($franchiseName) ?></span>
                                <span class="sagas-list__count">
                                    <?= (int) $item['game_count'] ?> jeu<?= (int) $item['game_count'] > 1 ? 'x' : '' ?>
                                </span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <p class="sagas-page__actions">
        <?php if ($searched): ?>
            <a href="<?= Moncine\View::escape(Moncine\View::gameFranchiseUrl('', $viewMode)) ?>" class="btn btn-secondary">Toutes les sagas</a>
        <?php endif; ?>
        <a href="/jeux.php" class="btn btn-secondary">Mes jeux</a>
    </p>
</section>
