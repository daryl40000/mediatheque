<?php
/**
 * Affichage des exemplaires (physique + démat) sur la fiche jeu.
 *
 * @var array<string, mixed> $game
 */
$physicalSupports = $game['physical_support_list'] ?? Moncine\GamePhysicalSupport::parseList((string) ($game['physical_supports'] ?? ''));
$digitalStores = $game['digital_store_list'] ?? Moncine\GameDigitalStore::parseStoredList((string) ($game['digital_stores'] ?? ''));
$hasAny = $physicalSupports !== [] || $digitalStores !== [] || !empty($game['has_digital_edition']);

/** @return string */
$physicalIconKey = static function (string $supportKey): string {
    return match ($supportKey) {
        Moncine\GamePhysicalSupport::CD_DVD => Moncine\GameEditionIcons::CD_DVD,
        Moncine\GamePhysicalSupport::DISKETTE => Moncine\GameEditionIcons::DISKETTE,
        default => '',
    };
};

/** @return string */
$digitalIconKey = static function (string $storeKey): string {
    return match ($storeKey) {
        Moncine\GameDigitalStore::STEAM => Moncine\GameEditionIcons::STEAM,
        Moncine\GameDigitalStore::GOG => Moncine\GameEditionIcons::GOG,
        Moncine\GameDigitalStore::EPIC => Moncine\GameEditionIcons::EPIC,
        default => '',
    };
};
?>
<?php if ($hasAny): ?>
    <section class="game-editions-display">
        <h2>Exemplaires</h2>
        <?php if ($physicalSupports !== []): ?>
            <h3 class="stats-subtitle">Physique</h3>
            <ul class="game-editions-display__list game-editions-display__list--icons">
                <?php foreach ($physicalSupports as $supportKey): ?>
                    <?php
                    $iconKey = $physicalIconKey($supportKey);
                    $label = Moncine\GamePhysicalSupport::label($supportKey);
                    ?>
                    <li class="game-editions-display__item">
                        <?php if ($iconKey !== ''): ?>
                            <span class="game-editions-display__icon">
                                <?php require MONCINE_ROOT . '/templates/_game_edition_icon.php'; ?>
                            </span>
                        <?php endif; ?>
                        <span class="game-editions-display__label"><?= Moncine\View::escape($label) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($digitalStores !== []): ?>
            <h3 class="stats-subtitle">Dématérialisé</h3>
            <ul class="game-editions-display__list game-editions-display__list--icons">
                <?php foreach ($digitalStores as $store): ?>
                    <?php
                    $storeKey = (string) ($store['store'] ?? '');
                    $iconKey = $digitalIconKey($storeKey);
                    $storeLabel = (string) ($store['label'] ?? Moncine\GameDigitalStore::label($storeKey));
                    $storeUrl = (string) ($store['url'] ?? '');
                    ?>
                    <li class="game-editions-display__item">
                        <?php if ($iconKey !== ''): ?>
                            <span class="game-editions-display__icon">
                                <?php require MONCINE_ROOT . '/templates/_game_edition_icon.php'; ?>
                            </span>
                        <?php endif; ?>
                        <span class="game-editions-display__label">
                            <?php if ($storeUrl !== ''): ?>
                                <a href="<?= Moncine\View::escape($storeUrl) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= Moncine\View::escape($storeLabel) ?>
                                </a>
                            <?php else: ?>
                                <?= Moncine\View::escape($storeLabel) ?>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php elseif (!empty($game['has_digital_edition'])): ?>
            <p class="hint">Version dématérialisée (détails non renseignés).</p>
        <?php endif; ?>
    </section>
<?php endif; ?>
