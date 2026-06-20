<?php
/**
 * Lien vers la page d’une saga jeux.
 *
 * @var string $franchiseName
 */
$franchiseName = trim((string) ($franchiseName ?? ''));
if ($franchiseName === ''): ?>
    —
<?php else: ?>
    <a href="<?= Moncine\View::escape(Moncine\View::gameFranchiseUrl($franchiseName)) ?>"
       class="saga-link"
       title="Voir tous les jeux de la saga « <?= Moncine\View::escape($franchiseName) ?> »"><?= Moncine\View::escape($franchiseName) ?></a>
<?php endif; ?>
