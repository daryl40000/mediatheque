<?php
/**
 * Filtres plateforme / magasin démat pour la recherche « Mes jeux ».
 *
 * @var Moncine\GameListFilter $listFilter
 */
$listFilter = $listFilter ?? Moncine\GameListFilter::empty();
$platformChoices = Moncine\GamePlatformRegistry::choices();
$platformKindChoices = Moncine\GameListFilter::platformKindChoices();
$pcStores = Moncine\GameDigitalStore::pcStoreChoices();
$consoleStores = [
    Moncine\GameDigitalStore::PSN => Moncine\GameDigitalStore::label(Moncine\GameDigitalStore::PSN),
    Moncine\GameDigitalStore::XBOX => Moncine\GameDigitalStore::label(Moncine\GameDigitalStore::XBOX),
    Moncine\GameDigitalStore::ESHOP => Moncine\GameDigitalStore::label(Moncine\GameDigitalStore::ESHOP),
];
?>
<div class="collection-search__filters">
    <div class="collection-search__filter">
        <label for="jeux_platform_kind">Type de plateforme</label>
        <select name="platform_kind" id="jeux_platform_kind">
            <option value="">Tous les types</option>
            <?php foreach ($platformKindChoices as $kindKey => $kindLabel): ?>
                <option value="<?= Moncine\View::escape($kindKey) ?>"
                    <?= ($listFilter->platformKind ?? '') === $kindKey ? ' selected' : '' ?>>
                    <?= Moncine\View::escape($kindLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="collection-search__filter">
        <label for="jeux_platform">Plateforme</label>
        <select name="platform" id="jeux_platform">
            <option value="">Toutes les plateformes</option>
            <?php foreach ($platformChoices as $platformKey => $platformLabel): ?>
                <option value="<?= Moncine\View::escape($platformKey) ?>"
                    <?= ($listFilter->platform ?? '') === $platformKey ? ' selected' : '' ?>>
                    <?= Moncine\View::escape($platformLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="collection-search__filter">
        <label for="jeux_store">Magasin démat.</label>
        <select name="store" id="jeux_store">
            <option value="">Tous les magasins</option>
            <optgroup label="PC">
                <?php foreach ($pcStores as $storeKey => $storeLabel): ?>
                    <option value="<?= Moncine\View::escape($storeKey) ?>"
                        <?= ($listFilter->store ?? '') === $storeKey ? ' selected' : '' ?>>
                        <?= Moncine\View::escape($storeLabel) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="Consoles">
                <?php foreach ($consoleStores as $storeKey => $storeLabel): ?>
                    <option value="<?= Moncine\View::escape($storeKey) ?>"
                        <?= ($listFilter->store ?? '') === $storeKey ? ' selected' : '' ?>>
                        <?= Moncine\View::escape($storeLabel) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        </select>
    </div>
</div>
