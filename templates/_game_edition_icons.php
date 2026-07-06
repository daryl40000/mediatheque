<?php
/**
 * Icônes support / magasin (liste jeux).
 *
 * @var list<string> $iconKeys
 * @var string $supplementalText
 * @var array<string, mixed>|null $game
 */
$iconKeys = $iconKeys ?? [];
$supplementalText = trim((string) ($supplementalText ?? ''));
$game = is_array($game ?? null) ? $game : [];
?>
<?php if ($iconKeys === [] && $supplementalText === ''): ?>
    <span class="hint">—</span>
<?php else: ?>
    <span class="game-edition-icons" role="list">
        <?php foreach ($iconKeys as $iconKey):
            $iconKey = (string) $iconKey;
            $iconLabel = Moncine\GameEditionIcons::label($iconKey);
            $iconLinkUrl = Moncine\GameEditionIcons::linkUrlForKey($iconKey, $game);
            ?>
            <span class="game-edition-icons__item" role="listitem"
                  title="<?= Moncine\View::escape($iconLabel) ?>">
                <?php if ($iconLinkUrl !== ''): ?>
                    <a class="game-edition-icons__link"
                       href="<?= Moncine\View::escape($iconLinkUrl) ?>"
                       target="_blank" rel="noopener noreferrer"
                       aria-label="<?= Moncine\View::escape($iconLabel . ' — ouvrir la page du magasin') ?>">
                        <?php require MONCINE_ROOT . '/templates/_game_edition_icon.php'; ?>
                    </a>
                <?php else: ?>
                    <?php require MONCINE_ROOT . '/templates/_game_edition_icon.php'; ?>
                <?php endif; ?>
            </span>
        <?php endforeach; ?>
        <?php if ($supplementalText !== ''): ?>
            <span class="game-edition-icons__text hint"><?= Moncine\View::escape($supplementalText) ?></span>
        <?php endif; ?>
    </span>
<?php endif; ?>
