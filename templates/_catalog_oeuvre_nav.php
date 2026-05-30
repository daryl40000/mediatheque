<?php
/**
 * Navigation précédent / suivant entre fiches catalogue.
 *
 * @var Moncine\CatalogListContext $catalogListContext
 * @var array{prev_id: int|null, next_id: int|null, position: int, total: int, in_list: bool} $oeuvreNav
 */
$total = (int) ($oeuvreNav['total'] ?? 0);
if ($total <= 1 || empty($oeuvreNav['in_list'])) {
    return;
}
$prevId = $oeuvreNav['prev_id'] ?? null;
$nextId = $oeuvreNav['next_id'] ?? null;
$position = (int) ($oeuvreNav['position'] ?? 0);
?>
<nav class="list-pager catalog-oeuvre-pager" aria-label="Œuvre précédente et suivante dans le catalogue">
    <div class="list-pager__group">
        <?php if ($prevId !== null): ?>
            <a href="<?= Moncine\View::escape($catalogListContext->oeuvreUrl((int) $prevId)) ?>"
               class="list-pager__link">← Précédent</a>
        <?php else: ?>
            <span class="list-pager__link is-disabled" aria-disabled="true">← Précédent</span>
        <?php endif; ?>

        <span class="list-pager__status"><?= $position ?> / <?= $total ?></span>

        <?php if ($nextId !== null): ?>
            <a href="<?= Moncine\View::escape($catalogListContext->oeuvreUrl((int) $nextId)) ?>"
               class="list-pager__link">Suivant →</a>
        <?php else: ?>
            <span class="list-pager__link is-disabled" aria-disabled="true">Suivant →</span>
        <?php endif; ?>
    </div>
</nav>
