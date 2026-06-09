<?php
/**
 * Affichage des exemplaires (physique + démat) sur la fiche jeu.
 *
 * @var array<string, mixed> $game
 */
$physicalLabels = $game['physical_support_labels'] ?? Moncine\GamePhysicalSupport::displayLabels((string) ($game['physical_supports'] ?? ''));
$digitalStores = $game['digital_store_list'] ?? Moncine\GameDigitalStore::parseStoredList((string) ($game['digital_stores'] ?? ''));
$hasAny = $physicalLabels !== [] || $digitalStores !== [] || !empty($game['has_digital_edition']);
?>
<?php if ($hasAny): ?>
    <section class="game-editions-display">
        <h2>Exemplaires</h2>
        <?php if ($physicalLabels !== []): ?>
            <h3 class="stats-subtitle">Physique</h3>
            <ul class="game-editions-display__list">
                <?php foreach ($physicalLabels as $label): ?>
                    <li><span class="magazine-tag magazine-tag--game-genre"><?= Moncine\View::escape((string) $label) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($digitalStores !== []): ?>
            <h3 class="stats-subtitle">Dématérialisé</h3>
            <ul class="game-editions-display__list">
                <?php foreach ($digitalStores as $store): ?>
                    <li>
                        <?php if ((string) ($store['url'] ?? '') !== ''): ?>
                            <a href="<?= Moncine\View::escape((string) $store['url']) ?>" target="_blank" rel="noopener noreferrer">
                                <?= Moncine\View::escape((string) ($store['label'] ?? '')) ?>
                            </a>
                        <?php else: ?>
                            <?= Moncine\View::escape((string) ($store['label'] ?? '')) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php elseif (!empty($game['has_digital_edition'])): ?>
            <p class="hint">Version dématérialisée (détails non renseignés).</p>
        <?php endif; ?>
    </section>
<?php endif; ?>
