<?php
/**
 * Liens magasins catalogue (Steam, GOG, Epic) — affichage public.
 *
 * @var array<string, mixed> $game
 */
$storeUrls = $game['catalog_store_urls'] ?? Moncine\CatalogGameStoreLinks::urlsForCatalogRow($game);
if ($storeUrls === []) {
    return;
}
?>
<section class="game-detail__store-links" aria-labelledby="catalog-store-links-heading">
    <h2 id="catalog-store-links-heading" class="game-detail__section-title">Disponible sur</h2>
    <ul class="game-store-links-list" role="list">
        <?php foreach ($storeUrls as $storeKey => $storeUrl): ?>
            <?php
            $iconKey = match ($storeKey) {
                Moncine\GameDigitalStore::STEAM => Moncine\GameEditionIcons::STEAM,
                Moncine\GameDigitalStore::GOG => Moncine\GameEditionIcons::GOG,
                Moncine\GameDigitalStore::EPIC => Moncine\GameEditionIcons::EPIC,
                default => '',
            };
            $label = Moncine\GameDigitalStore::label((string) $storeKey);
            $iconUrl = $iconKey !== '' ? Moncine\GameEditionIcons::iconImageUrl($iconKey) : '';
            ?>
            <li class="game-store-links-list__item" role="listitem">
                <a class="game-store-links-list__link" href="<?= Moncine\View::escape((string) $storeUrl) ?>"
                   target="_blank" rel="noopener noreferrer">
                    <?php if ($iconUrl !== ''): ?>
                        <img class="game-store-links-list__icon" src="<?= Moncine\View::escape($iconUrl) ?>"
                             alt="" width="24" height="24" loading="lazy">
                    <?php endif; ?>
                    <span><?= Moncine\View::escape($label) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
