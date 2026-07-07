<?php
/**
 * Liens magasins PC sur une fiche jeu catalogue (saisie manuelle).
 *
 * @var array<string, mixed> $game
 * @var int $oeuvreId
 * @var string $catalogSearch
 * @var string $catalogSort
 * @var string $catalogDir
 * @var int $catalogPage
 * @var bool $storeLinksSaved
 * @var string $storeLinksError
 */
$storeLinksSaved = !empty($storeLinksSaved);
$storeLinksError = trim((string) ($storeLinksError ?? ''));
$storeUrls = Moncine\CatalogGameStoreLinks::urlsForCatalogRow($game);
$catalogMedia = isset($catalogListContext) ? $catalogListContext->mediaDomain() : '';
?>
<details class="film-edit-panel catalog-game-store-links">
    <summary class="film-edit-panel__summary">Liens magasins (Steam, GOG, Epic)</summary>

    <p class="hint">
        Collez l’adresse de la page du jeu sur chaque magasin (fiche catalogue partagée).
        Cela n’ajoute pas le jeu à votre bibliothèque : pour indiquer que vous possédez le jeu,
        cochez les cases dans votre fiche bibliothèque (« Exemplaires possédés »).
        Laissez vide pour ne rien changer. Cochez « Retirer » pour supprimer un lien existant.
    </p>

    <?php if ($storeLinksSaved): ?>
        <div class="alert alert-success">Liens magasins enregistrés.</div>
    <?php endif; ?>
    <?php if ($storeLinksError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($storeLinksError) ?></div>
    <?php endif; ?>

    <form method="post" action="/enregistrer-liens-magasin-catalogue.php" class="import-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="oeuvre_id" value="<?= (int) $oeuvreId ?>">
        <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch ?? '') ?>">
        <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort ?? 'titre') ?>">
        <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir ?? 'asc') ?>">
        <input type="hidden" name="catalog_page" value="<?= max(1, (int) ($catalogPage ?? 1)) ?>">
        <?php if ($catalogMedia !== ''): ?>
            <input type="hidden" name="catalog_media" value="<?= Moncine\View::escape($catalogMedia) ?>">
        <?php endif; ?>

        <?php foreach (Moncine\CatalogGameStoreLinks::MANUAL_STORES as $storeKey): ?>
            <?php
            $label = Moncine\GameDigitalStore::label($storeKey);
            $currentUrl = trim((string) ($storeUrls[$storeKey] ?? ''));
            $placeholder = match ($storeKey) {
                Moncine\GameDigitalStore::STEAM => 'https://store.steampowered.com/app/…',
                Moncine\GameDigitalStore::GOG => 'https://www.gog.com/game/…',
                Moncine\GameDigitalStore::EPIC => 'https://store.epicgames.com/p/…',
                default => 'https://…',
            };
            ?>
            <div class="catalog-game-store-links__row">
                <label for="store_url_<?= Moncine\View::escape($storeKey) ?>"><?= Moncine\View::escape($label) ?></label>
                <input type="url" name="store_url_<?= Moncine\View::escape($storeKey) ?>"
                       id="store_url_<?= Moncine\View::escape($storeKey) ?>"
                       maxlength="500" placeholder="<?= Moncine\View::escape($placeholder) ?>"
                       value="<?= Moncine\View::escape($currentUrl) ?>">
                <?php if ($currentUrl !== ''): ?>
                    <p class="hint">
                        <a href="<?= Moncine\View::escape($currentUrl) ?>" target="_blank" rel="noopener">Ouvrir le lien actuel</a>
                    </p>
                <?php endif; ?>
                <label class="checkbox-inline">
                    <input type="checkbox" name="clear_store_<?= Moncine\View::escape($storeKey) ?>" value="1">
                    Retirer ce lien
                </label>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary">Enregistrer les liens magasins</button>
    </form>
</details>
