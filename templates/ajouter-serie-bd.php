<?php
/** @var array<string, string> $kindChoices */
/** @var array<string, mixed> $series */
/** @var string $error */
/** @var bool $moduleAvailable */
/** @var string $catalogAddedMessage */
/** @var string $statut */
$statut = $statut ?? Moncine\LibraryStatut::COLLECTION;
$isWishlist = $statut === Moncine\LibraryStatut::WISHLIST;
?>
<section>
    <h1><?= $isWishlist ? 'Ajouter une envie BD' : 'Ajouter une série BD / manga' ?></h1>
    <p class="lead">
        Commencez par créer la <strong>série</strong> (ex. Astérix, One Piece).
        Vous pourrez ensuite y ajouter les tomes un par un.
    </p>
    <p><a href="<?= $isWishlist ? '/bd-envies.php' : '/bd.php' ?>" class="btn btn-secondary btn-sm">← Retour</a></p>

    <?php if ($catalogAddedMessage !== ''): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($catalogAddedMessage) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
    <?php endif; ?>

    <?php if ($moduleAvailable): ?>
    <details class="catalog-admin-panel" open>
        <summary class="catalog-admin-panel__summary">Ajouter une série du catalogue</summary>
        <div class="catalog-admin-panel__body">
            <p class="hint">Si la série existe déjà au catalogue partagé, sélectionnez-la ici.</p>
            <form method="post" action="/enregistrer-serie-bd.php" class="import-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="from_catalog">
                <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">
                <input type="hidden" name="catalog_series_id" id="bd_catalog_series_id" value="">

                <label for="bd_catalog_series_search">Rechercher au catalogue</label>
                <div class="catalog-title-autocomplete magazine-series-catalog-autocomplete"
                     data-magazine-series-catalog-autocomplete
                     data-search-url="<?= Moncine\View::escape(Moncine\View::bdSeriesCatalogApiUrl()) ?>"
                     data-series-id-input="bd_catalog_series_id">
                    <input type="search" id="bd_catalog_series_search"
                           autocomplete="off" placeholder="Ex. Astérix, One Piece…"
                           aria-controls="bd-catalog-series-suggestions">
                    <ul class="catalog-title-autocomplete__list" id="bd-catalog-series-suggestions"
                        role="listbox" hidden></ul>
                </div>
                <button type="submit" class="btn btn-primary" id="bd_catalog_series_submit" disabled>
                    Ajouter à mes BD
                </button>
            </form>
        </div>
    </details>
    <?php endif; ?>

    <details class="catalog-admin-panel"<?= $moduleAvailable ? '' : ' open' ?>>
        <summary class="catalog-admin-panel__summary">Créer une nouvelle série</summary>
        <div class="catalog-admin-panel__body">
            <form method="post" action="/enregistrer-serie-bd.php" class="import-form" enctype="multipart/form-data">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">

                <label for="bd_series_titre">Titre de la série <span class="required">*</span></label>
                <input type="text" name="titre" id="bd_series_titre" required
                       value="<?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?>">

                <label for="bd_series_kind">Type</label>
                <select name="kind" id="bd_series_kind">
                    <?php foreach ($kindChoices as $key => $label): ?>
                        <option value="<?= Moncine\View::escape($key) ?>"
                            <?= (($series['kind'] ?? 'bd') === $key) ? ' selected' : '' ?>>
                            <?= Moncine\View::escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="bd_series_editeur">Éditeur (optionnel)</label>
                <input type="text" name="editeur" id="bd_series_editeur"
                       value="<?= Moncine\View::escape((string) ($series['editeur'] ?? '')) ?>">

                <label for="bd_series_notes">Notes (optionnel)</label>
                <textarea name="notes" id="bd_series_notes" rows="3"><?= Moncine\View::escape((string) ($series['notes'] ?? '')) ?></textarea>

                <label for="bd_series_cover">Couverture de la série (optionnel)</label>
                <input type="file" name="cover_file" id="bd_series_cover" accept="image/*">

                <button type="submit" class="btn btn-primary"<?= $moduleAvailable ? '' : ' disabled' ?>>
                    Créer la série
                </button>
            </form>
        </div>
    </details>
</section>
