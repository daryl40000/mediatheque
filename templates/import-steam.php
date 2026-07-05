<?php
/**
 * @var list<array<string, mixed>> $importRows
 * @var list<array<string, mixed>> $proposalRows
 * @var array{total: int, in_library: int, catalog_only: int, new: int, with_igdb: int} $summary
 * @var bool $canCreateCatalogEntries
 * @var string $mapMessage
 * @var string $mapError
 */
?>
<section class="import-page" data-steam-import-page data-catalog-search-url="/rechercher-jeux-catalogue.php">
    <h1>Import Steam — aperçu</h1>
    <p class="lead">
        <?= (int) ($summary['total'] ?? 0) ?> jeu(x) dans votre bibliothèque Steam.
        <?php if (!empty($canCreateCatalogEntries)): ?>
            Cochez ceux à importer ou à créer au catalogue, puis validez.
        <?php else: ?>
            Les jeux déjà au catalogue peuvent être ajoutés à votre collection ;
            les autres peuvent être <strong>liés manuellement</strong> ou <strong>proposés</strong> aux administrateurs.
        <?php endif; ?>
    </p>

    <?php if (($mapMessage ?? '') !== ''): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($mapMessage) ?></div>
    <?php endif; ?>
    <?php if (($mapError ?? '') !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($mapError) ?></div>
    <?php endif; ?>

    <ul class="hint">
        <li>Déjà dans ma collection : <?= (int) ($summary['in_library'] ?? 0) ?> (mise à jour Steam + temps de jeu)</li>
        <li>Au catalogue seulement : <?= (int) ($summary['catalog_only'] ?? 0) ?> (ajout à la collection)</li>
        <?php if (!empty($canCreateCatalogEntries)): ?>
            <li>Absents du catalogue : <?= (int) ($summary['new'] ?? 0) ?> (création fiche + ajout)</li>
        <?php else: ?>
            <li>Absents du catalogue : <?= (int) ($summary['new'] ?? 0) ?> (lien manuel ou proposition)</li>
        <?php endif; ?>
        <li>Avec correspondance IGDB : <?= (int) ($summary['with_igdb'] ?? 0) ?></li>
    </ul>

    <?php if ($proposalRows !== []): ?>
    <h2>Relier au catalogue</h2>
    <p class="hint">
        Le jeu existe peut-être déjà sous un autre titre (français, édition…).
        Cherchez-le ci-dessous : le lien sera mémorisé pour les prochains imports Steam.
    </p>

    <div class="steam-map-list">
        <?php foreach ($proposalRows as $row): ?>
            <?php $appid = (int) ($row['appid'] ?? 0); ?>
            <div class="catalog-admin-panel steam-map-panel" data-steam-map-root>
                <p class="steam-map-panel__title">
                    <strong><?= Moncine\View::escape((string) ($row['name'] ?? '')) ?></strong>
                    <span class="hint"> — AppID <?= $appid ?></span>
                </p>
                <form method="post" action="/import-steam-actions.php" class="import-form steam-map-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="save_steam_mapping">
                    <input type="hidden" name="steam_appid" value="<?= $appid ?>">
                    <input type="hidden" name="oeuvre_id" value="" data-steam-map-oeuvre-id>

                    <label for="steam_map_search_<?= $appid ?>">Jeu du catalogue</label>
                    <div class="catalog-title-autocomplete" data-steam-map-autocomplete>
                        <input type="search" id="steam_map_search_<?= $appid ?>" class="catalog-title-autocomplete__input"
                               data-steam-map-search autocomplete="off" placeholder="Titre du jeu au catalogue…">
                        <div class="catalog-title-autocomplete__list" data-steam-map-list hidden role="listbox"></div>
                    </div>
                    <p class="hint steam-map-selected" data-steam-map-hint hidden></p>

                    <button type="submit" class="btn btn-secondary btn-sm">Enregistrer le lien</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" action="/import-steam-actions.php" class="import-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="action" value="apply_steam_import">

        <?php if ($importRows !== []): ?>
        <h2>Ajouter à ma bibliothèque</h2>
        <p class="hint">Jeux reconnus dans le catalogue partagé (automatiquement ou via un lien manuel).</p>

        <p class="export-actions">
            <button type="button" class="btn btn-secondary btn-sm steam-select-import-all">Tout cocher</button>
            <button type="button" class="btn btn-secondary btn-sm steam-select-import-none">Tout décocher</button>
        </p>

        <div class="table-scroll">
            <table class="films-table films-table--wide">
                <thead>
                    <tr>
                        <th class="col-select" scope="col">Importer</th>
                        <th scope="col">Jeu</th>
                        <th scope="col">Temps Steam</th>
                        <th scope="col">Action</th>
                        <th scope="col">IGDB</th>
                        <th scope="col">Correspondance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($importRows as $row): ?>
                        <?php $appid = (int) ($row['appid'] ?? 0); ?>
                        <tr>
                            <td class="col-select">
                                <input type="checkbox" name="import_appids[]" value="<?= $appid ?>" checked
                                       class="steam-import-checkbox"
                                       aria-label="Importer <?= Moncine\View::escape((string) ($row['name'] ?? '')) ?>">
                            </td>
                            <td><?= Moncine\View::escape((string) ($row['name'] ?? '')) ?></td>
                            <td><?= Moncine\View::escape((string) ($row['playtime_label'] ?? '')) ?></td>
                            <td><?= Moncine\View::escape((string) ($row['action_label'] ?? '')) ?></td>
                            <td><?= (int) ($row['igdb_id'] ?? 0) > 0 ? 'Oui' : '—' ?></td>
                            <td><?= Moncine\View::escape(Moncine\SteamLibraryImporter::matchTypeLabel((string) ($row['match_type'] ?? ''))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($proposalRows !== []): ?>
        <h2><?= !empty($canCreateCatalogEntries) ? 'Créer au catalogue' : 'Proposer au catalogue' ?></h2>
        <p class="hint">
            <?php if (!empty($canCreateCatalogEntries)): ?>
                Jeux toujours introuvables — une fiche catalogue sera créée puis ajoutée à votre collection.
            <?php else: ?>
                Si le jeu n’existe vraiment pas, envoyez une proposition aux administrateurs
                (<a href="/proposer-jeu.php">comme « Proposer un jeu »</a>).
            <?php endif; ?>
        </p>

        <p class="export-actions">
            <button type="button" class="btn btn-secondary btn-sm steam-select-propose-all">Tout cocher</button>
            <button type="button" class="btn btn-secondary btn-sm steam-select-propose-none">Tout décocher</button>
        </p>

        <div class="table-scroll">
            <table class="films-table films-table--wide">
                <thead>
                    <tr>
                        <th class="col-select" scope="col"><?= !empty($canCreateCatalogEntries) ? 'Créer' : 'Proposer' ?></th>
                        <th scope="col">Jeu</th>
                        <th scope="col">Temps Steam</th>
                        <th scope="col">IGDB</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proposalRows as $row): ?>
                        <?php $appid = (int) ($row['appid'] ?? 0); ?>
                        <tr>
                            <td class="col-select">
                                <input type="checkbox" name="propose_appids[]" value="<?= $appid ?>"
                                       class="steam-propose-checkbox"
                                       aria-label="<?= !empty($canCreateCatalogEntries) ? 'Créer' : 'Proposer' ?> <?= Moncine\View::escape((string) ($row['name'] ?? '')) ?>">
                            </td>
                            <td><?= Moncine\View::escape((string) ($row['name'] ?? '')) ?></td>
                            <td><?= Moncine\View::escape((string) ($row['playtime_label'] ?? '')) ?></td>
                            <td><?= (int) ($row['igdb_id'] ?? 0) > 0 ? 'Oui' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($importRows === [] && $proposalRows === []): ?>
            <p class="hint">Aucun jeu à traiter.</p>
        <?php else: ?>
        <p class="export-actions">
            <button type="submit" class="btn btn-accent">Valider la sélection</button>
            <a href="/import.php" class="btn btn-secondary">Annuler</a>
        </p>
        <?php endif; ?>
    </form>
</section>
<script>
(function () {
    function bindGroup(selectAllClass, selectNoneClass, checkboxClass) {
        const allBtn = document.querySelector(selectAllClass);
        const noneBtn = document.querySelector(selectNoneClass);
        const boxes = document.querySelectorAll(checkboxClass);
        if (allBtn) {
            allBtn.addEventListener('click', function () {
                boxes.forEach(function (box) { box.checked = true; });
            });
        }
        if (noneBtn) {
            noneBtn.addEventListener('click', function () {
                boxes.forEach(function (box) { box.checked = false; });
            });
        }
    }
    bindGroup('.steam-select-import-all', '.steam-select-import-none', '.steam-import-checkbox');
    bindGroup('.steam-select-propose-all', '.steam-select-propose-none', '.steam-propose-checkbox');
})();
</script>
