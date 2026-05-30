<?php
/**
 * @var list<array<string, mixed>> $oeuvres
 * @var string $search
 * @var string $sortBy
 * @var string $sortDir
 * @var int $page
 * @var int $totalPages
 * @var int $totalCount
 * @var int $perPage
 * @var bool $added
 * @var bool $deleted
 * @var string $saveError
 * @var string $deleteError
 * @var bool $hasTmdbKey
 */

$admin = new Moncine\CatalogAdmin();

$sortHeader = static function (string $label, string $column) use ($sortBy, $sortDir, $search, $page, $admin): void {
    $active = $sortBy === $column;
    $aria = $active
        ? (strtolower($sortDir) === 'desc' ? 'descending' : 'ascending')
        : 'none';
    $indicator = '';
    if ($active) {
        $indicator = strtolower($sortDir) === 'desc' ? ' ↓' : ' ↑';
    }
    ?>
    <th class="<?= $active ? 'sorted' : '' ?>" aria-sort="<?= $aria ?>">
        <a href="<?= Moncine\View::escape($admin->sortUrl($column, $sortBy, $sortDir, $search, $page)) ?>">
            <?= Moncine\View::escape($label) ?><?= $indicator ?>
        </a>
    </th>
    <?php
};

?>
<section class="catalog-admin-page">
    <div class="catalog-admin-page__head">
        <div>
            <h1>Catalogue Moncine</h1>
            <p class="lead">
                Fiches partagées (titre, réalisateur, TMDB…) utilisées par toute l’application.
                Les utilisateurs s’y rattachent via <strong>Mes films</strong> ou <strong>Mes envies</strong>.
            </p>
        </div>
        <p class="catalog-admin-page__badge hint">
            Page réservée à l’administrateur (pour l’instant : compte principal uniquement).
        </p>
    </div>

    <?php if ($added): ?>
        <p class="alert alert-success">Œuvre ajoutée au catalogue.</p>
    <?php endif; ?>
    <?php if ($deleted): ?>
        <p class="alert alert-success">Œuvre supprimée du catalogue.</p>
    <?php endif; ?>
    <?php if ($saveError !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></p>
    <?php endif; ?>
    <?php if ($deleteError !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($deleteError) ?></p>
    <?php endif; ?>

    <details class="catalog-admin-panel"<?= ($added || $saveError !== '') ? ' open' : '' ?>>
        <summary class="catalog-admin-panel__summary">Ajouter une œuvre au catalogue</summary>
        <div class="catalog-admin-panel__body">
            <p class="hint">
                Cette action n’ajoute pas le film à vos films : elle crée seulement la fiche catalogue.
                Utilisez l’autocomplétion pour éviter les doublons (titre — réalisateur).
            </p>

            <?php if (!$hasTmdbKey): ?>
                <p class="alert alert-info">
                    <a href="/import.php">Configurez une clé API TMDB</a> pour enrichir les fiches après création.
                </p>
            <?php endif; ?>

            <form method="post" action="/enregistrer-catalogue.php" class="film-edit-form import-form catalog-admin-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($search) ?>">

                <fieldset>
                    <legend>Informations principales</legend>

                    <?php
                    $fieldPrefix = 'add';
                    $film = [];
                    require MONCINE_ROOT . '/templates/_film_content_kind_fields.php';
                    ?>

                    <label for="add_titre">Titre <span class="required">*</span></label>
                    <input type="hidden" name="oeuvre_id" id="add_oeuvre_id" value="">
                    <div class="catalog-title-autocomplete" id="catalog-title-autocomplete"
                         data-search-url="/rechercher-oeuvres.php">
                        <input type="text" name="titre" id="add_titre" required
                               class="catalog-title-autocomplete__input"
                               autocomplete="off" autocapitalize="off" spellcheck="false"
                               placeholder="Tapez le titre — ne choisissez pas une œuvre déjà listée"
                               aria-autocomplete="list" aria-controls="catalog-title-suggestions"
                               aria-expanded="false">
                        <ul class="catalog-title-autocomplete__list" id="catalog-title-suggestions"
                            role="listbox" hidden></ul>
                    </div>

                    <label for="add_realisateur">Réalisateur</label>
                    <input type="text" name="realisateur" id="add_realisateur"
                           placeholder="ex. Francis Ford Coppola">

                    <label for="add_annee">Année</label>
                    <input type="text" name="annee" id="add_annee" inputmode="numeric" pattern="[0-9]{4}"
                           placeholder="1972">

                    <label for="add_styles">Style(s)</label>
                    <input type="text" name="styles" id="add_styles" placeholder="Drame, Policier">
                </fieldset>

                <details class="catalog-admin-form__more">
                    <summary>Champs optionnels (synopsis, affiche, TMDB…)</summary>
                    <fieldset>
                        <label for="add_titre_original">Titre original</label>
                        <input type="text" name="titre_original" id="add_titre_original">

                        <label for="add_acteur_1">Acteur principal</label>
                        <input type="text" name="acteur_1" id="add_acteur_1">

                        <label for="add_duree">Durée</label>
                        <input type="text" name="duree" id="add_duree" placeholder="1h56 ou 116">

                        <label for="add_poster_url">Affiche (URL HTTPS)</label>
                        <input type="text" name="poster_url" id="add_poster_url"
                               placeholder="https://…">

                        <label for="add_synopsis">Synopsis</label>
                        <textarea name="synopsis" id="add_synopsis" rows="3"></textarea>

                        <label for="add_tmdb">Identifiant TMDB</label>
                        <input type="text" name="tmdb_id" id="add_tmdb" placeholder="78 ou /movie/78">
                    </fieldset>
                </details>

                <?php
                $submitLabel = 'Ajouter au catalogue';
                $enrichLabel = 'Ajouter avec enrichissement';
                $cancelUrl = '/catalogue.php' . ($search !== '' ? '?q=' . rawurlencode($search) : '');
                require MONCINE_ROOT . '/templates/_film_save_actions.php';
                ?>
            </form>
        </div>
    </details>

    <section class="catalog-admin-list">
        <h2>Œuvres enregistrées</h2>

        <form method="get" action="/catalogue.php" class="collection-search import-form">
            <label for="catalog_search_q">Rechercher dans le catalogue</label>
            <div class="collection-search__row">
                <input type="search" name="q" id="catalog_search_q"
                       value="<?= Moncine\View::escape($search) ?>"
                       placeholder="Titre ou réalisateur…"
                       autocomplete="off">
                <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
                <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
                <button type="submit" class="btn btn-primary">Rechercher</button>
                <?php if ($search !== ''): ?>
                    <a href="/catalogue.php?sort=<?= Moncine\View::escape($sortBy) ?>&amp;dir=<?= Moncine\View::escape($sortDir) ?>"
                       class="btn btn-secondary">Effacer</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($totalCount === 0): ?>
            <p class="alert alert-warning">
                <?= $search !== ''
                    ? 'Aucune œuvre ne correspond à cette recherche.'
                    : 'Le catalogue est vide. Ajoutez une première œuvre ci-dessus.' ?>
            </p>
        <?php else: ?>
            <p class="stats">
                <?= $totalCount ?> œuvre<?= $totalCount > 1 ? 's' : '' ?>
                <?php if ($search !== ''): ?>
                    pour « <strong><?= Moncine\View::escape($search) ?></strong> »
                <?php endif; ?>
            </p>

            <?php if ($totalPages > 1): ?>
                <div id="catalog-list-nav" class="catalog-list-nav-anchor">
                    <?php
                    $paginationIdSuffix = '-top';
                    require MONCINE_ROOT . '/templates/_catalog_admin_pagination.php';
                    ?>
                </div>
            <?php endif; ?>

            <p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
            <div id="catalogue-list" class="table-scroll catalogue-list-anchor">
                <table class="films-table films-table--sortable catalog-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <?php $sortHeader('Titre', 'titre'); ?>
                            <?php $sortHeader('Réalisateur', 'realisateur'); ?>
                            <?php $sortHeader('Année', 'annee'); ?>
                            <th scope="col">Type</th>
                            <th scope="col">Bibliothèques</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($oeuvres as $oeuvre):
                            $oeuvreId = (int) ($oeuvre['id'] ?? 0);
                            $libraryCount = (int) ($oeuvre['library_count'] ?? 0);
                            ?>
                            <tr>
                                <td><?= $oeuvreId ?></td>
                                <td>
                                    <a href="<?= Moncine\View::escape(Moncine\View::oeuvreUrl(
                                        $oeuvreId,
                                        $search,
                                        $sortBy,
                                        $sortDir,
                                        $page
                                    )) ?>" class="film-link catalog-admin-table__title">
                                        <?= Moncine\View::escape((string) ($oeuvre['titre'] ?? '')) ?>
                                    </a>
                                    <?php if (trim((string) ($oeuvre['titre_original'] ?? '')) !== ''): ?>
                                        <br><span class="hint"><?= Moncine\View::escape((string) $oeuvre['titre_original']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= ($oeuvre['realisateur'] ?? '') !== ''
                                    ? Moncine\View::escape((string) $oeuvre['realisateur'])
                                    : '—' ?></td>
                                <td><?= (int) ($oeuvre['annee'] ?? 0) > 0 ? (int) $oeuvre['annee'] : '—' ?></td>
                                <td><?= Moncine\View::escape(
                                    Moncine\View::contentKindLabel($oeuvre)
                                ) ?></td>
                                <td>
                                    <?php if ($libraryCount > 0): ?>
                                        <span class="tag"><?= $libraryCount ?> lien<?= $libraryCount > 1 ? 's' : '' ?></span>
                                    <?php else: ?>
                                        <span class="hint">Aucun</span>
                                    <?php endif; ?>
                                </td>
                                <td class="catalog-admin-table__actions">
                                    <form method="post" action="/catalogue.php" class="inline-form"
                                          onsubmit="return confirm(<?= json_encode(
                                              'Supprimer « ' . (string) ($oeuvre['titre'] ?? '') . ' » du catalogue ?'
                                              . ($libraryCount > 0
                                                  ? "\n\n" . $libraryCount . ' entrée(s) bibliothèque seront aussi supprimées (mes films / mes envies).'
                                                  : '')
                                          ) ?>);">
                                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                        <input type="hidden" name="action" value="delete_oeuvre">
                                        <input type="hidden" name="oeuvre_id" value="<?= $oeuvreId ?>">
                                        <input type="hidden" name="q" value="<?= Moncine\View::escape($search) ?>">
                                        <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
                                        <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
                                        <button type="submit" class="btn btn-icon btn-danger-text"
                                                title="Supprimer du catalogue"
                                                aria-label="Supprimer « <?= Moncine\View::escape((string) ($oeuvre['titre'] ?? '')) ?> » du catalogue">
                                            <svg class="icon-trash" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $paginationIdSuffix = '-bottom';
            require MONCINE_ROOT . '/templates/_catalog_admin_pagination.php';
            ?>
        <?php endif; ?>
    </section>

    <p class="collection-page__footer-links">
        <a href="/films.php">← Mes films</a>
        <a href="/maintenance-catalogue.php">Maintenance catalogue</a>
        <a href="/ajouter-film.php">Ajouter à ma bibliothèque</a>
        <a href="/import.php">Importer / exporter (catalogue)</a>
    </p>
</section>
