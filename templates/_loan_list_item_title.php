<?php
/**
 * Titre + sous-titre (plateforme jeu) dans les listes de prêts.
 *
 * @var array<string, mixed> $row
 */
$row = $row ?? [];
$title = (string) ($row['titre'] ?? '');
$subtitle = Moncine\LoanEligibility::listSubtitle($row);
?>
<span class="user-search-results__name">
    <?= Moncine\View::escape($title) ?>
    <?php if ($subtitle !== ''): ?>
        <span class="hint">(<?= Moncine\View::escape($subtitle) ?>)</span>
    <?php endif; ?>
</span>
