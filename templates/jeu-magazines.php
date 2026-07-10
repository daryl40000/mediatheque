<?php
/**
 * Liste des magazines qui traitent un jeu.
 *
 * @var array<string, mixed>|null $game
 * @var string $gameTitle
 * @var list<array<string, mixed>> $issues
 * @var string $backUrl
 */
?>
<section class="collection-page game-detail-page">
    <?php if ($game === null): ?>
        <h1>Jeu introuvable</h1>
        <p class="hint">Ce jeu n’existe pas ou n’est plus dans le catalogue.</p>
        <p><a href="/jeux.php" class="btn btn-secondary">← Mes jeux</a></p>
    <?php else: ?>
        <header class="collection-page__header">
            <p>
                <a href="<?= Moncine\View::escape($backUrl) ?>" class="btn btn-secondary btn-sm">← <?= Moncine\View::escape($gameTitle) ?></a>
            </p>
            <h1>Magazines</h1>
            <p class="hint">
                Revues qui parlent de <strong><?= Moncine\View::escape($gameTitle) ?></strong>
                (<?= count($issues) ?> numéro<?= count($issues) > 1 ? 's' : '' ?>).
            </p>
        </header>

        <?php if ($issues === []): ?>
            <p class="hint">Aucun magazine relié pour l’instant.</p>
        <?php else: ?>
            <?php
            $magazineCoverageRows = $issues;
            require MONCINE_ROOT . '/templates/_game_magazine_issues_grid.php';
            ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
