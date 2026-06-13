<?php
/**
 * Icônes support / magasin (liste jeux).
 *
 * @var list<string> $iconKeys
 * @var string $supplementalText
 */
$iconKeys = $iconKeys ?? [];
$supplementalText = trim((string) ($supplementalText ?? ''));
?>
<?php if ($iconKeys === [] && $supplementalText === ''): ?>
    <span class="hint">—</span>
<?php else: ?>
    <span class="game-edition-icons" role="list">
        <?php foreach ($iconKeys as $iconKey): ?>
            <span class="game-edition-icons__item" role="listitem"
                  title="<?= Moncine\View::escape(Moncine\GameEditionIcons::label((string) $iconKey)) ?>">
                <?php require MONCINE_ROOT . '/templates/_game_edition_icon.php'; ?>
            </span>
        <?php endforeach; ?>
        <?php if ($supplementalText !== ''): ?>
            <span class="game-edition-icons__text hint"><?= Moncine\View::escape($supplementalText) ?></span>
        <?php endif; ?>
    </span>
<?php endif; ?>
