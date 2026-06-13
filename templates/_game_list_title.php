<?php
/**
 * Titre cliquable + badge Linux optionnel (listes jeux).
 *
 * @var array<string, mixed> $game
 * @var int|null $bibId
 */
$bibId = (int) ($bibId ?? ($game['id'] ?? 0));
$titre = (string) ($game['display_label'] ?? $game['titre'] ?? '');
?>
<span class="game-list-title">
    <a href="<?= Moncine\View::escape(Moncine\View::gameUrl($bibId)) ?>">
        <?= Moncine\View::escape($titre) ?>
    </a>
    <?php if ((string) ($game['linux_badge'] ?? '') !== '' || !empty($game['tested_on_linux']) || !empty($game['linux_not_supported'])): ?>
        <?php
        $size = 'sm';
        $plain = true;
        require MONCINE_ROOT . '/templates/_game_linux_badge_if_set.php';
        ?>
    <?php endif; ?>
</span>
