<?php
/**
 * Filtres plateforme / support / magasin démat pour la recherche « Mes jeux ».
 *
 * @var Moncine\GameListFilter $listFilter
 */
$listFilter = $listFilter ?? Moncine\GameListFilter::empty();
$platformChoices = Moncine\GamePlatformRegistry::choices();
$supportChoices = Moncine\GameListFilter::supportChoices();
$pcStores = Moncine\GameDigitalStore::pcStoreChoices();
$consoleStores = [
    Moncine\GameDigitalStore::PSN => Moncine\GameDigitalStore::label(Moncine\GameDigitalStore::PSN),
    Moncine\GameDigitalStore::XBOX => Moncine\GameDigitalStore::label(Moncine\GameDigitalStore::XBOX),
    Moncine\GameDigitalStore::ESHOP => Moncine\GameDigitalStore::label(Moncine\GameDigitalStore::ESHOP),
];
?>
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
        <label for="jeux_support">Type de support</label>
        <select name="support" id="jeux_support">
            <option value="">Tous les supports</option>
            <?php foreach ($supportChoices as $supportKey => $supportLabel): ?>
                <option value="<?= Moncine\View::escape($supportKey) ?>"
                    <?= ($listFilter->support ?? '') === $supportKey ? ' selected' : '' ?>>
                    <?= Moncine\View::escape($supportLabel) ?>
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
