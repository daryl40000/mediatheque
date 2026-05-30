<?php
/**
 * Navigation précédent / suivant entre fiches d’une même liste.
 *
 * @var Moncine\FilmListContext $filmListContext
 * @var array{prev_id: int|null, next_id: int|null, position: int, total: int, in_list: bool} $filmNav
 */
$total = (int) ($filmNav['total'] ?? 0);
if ($total <= 1 || empty($filmNav['in_list'])) {
    return;
}
$prevId = $filmNav['prev_id'] ?? null;
$nextId = $filmNav['next_id'] ?? null;
$position = (int) ($filmNav['position'] ?? 0);
?>
<nav class="list-pager film-list-pager" aria-label="Film précédent et suivant dans la liste">
    <div class="list-pager__group">
        <?php if ($prevId !== null): ?>
            <a href="<?= Moncine\View::escape($filmListContext->filmUrl((int) $prevId)) ?>"
               class="list-pager__link">← Précédent</a>
        <?php else: ?>
            <span class="list-pager__link is-disabled" aria-disabled="true">← Précédent</span>
        <?php endif; ?>

        <span class="list-pager__status"><?= $position ?> / <?= $total ?></span>

        <?php if ($nextId !== null): ?>
            <a href="<?= Moncine\View::escape($filmListContext->filmUrl((int) $nextId)) ?>"
               class="list-pager__link">Suivant →</a>
        <?php else: ?>
            <span class="list-pager__link is-disabled" aria-disabled="true">Suivant →</span>
        <?php endif; ?>
    </div>
</nav>
