<?php
/**
 * Affichage lecture seule des versions recherchées (partage visiteur, etc.).
 *
 * @var list<array<string, mixed>> $wishlistTargets
 * @var string $emptyHint
 */
$wishlistTargets = $wishlistTargets ?? [];
$emptyHint = $emptyHint ?? 'Aucune version précisée.';
?>
<?php if ($wishlistTargets === []): ?>
    <p class="hint"><?= Moncine\View::escape($emptyHint) ?></p>
<?php else: ?>
    <ul class="wishlist-targets-readonly">
        <?php foreach ($wishlistTargets as $row): ?>
            <li>
                <strong><?= Moncine\View::escape(
                    Moncine\SupportPhysique::label((string) ($row['support_physique'] ?? ''))
                ) ?></strong>
                <?php if (trim((string) ($row['ean'] ?? '')) !== ''): ?>
                    — <code><?= Moncine\View::escape(
                        Moncine\View::formatEan((string) ($row['ean'] ?? ''))
                    ) ?></code>
                <?php else: ?>
                    <span class="hint">(EAN non précisé)</span>
                <?php endif; ?>
                <?php if (trim((string) ($row['label'] ?? '')) !== ''): ?>
                    <span class="hint">— <?= Moncine\View::escape((string) $row['label']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
