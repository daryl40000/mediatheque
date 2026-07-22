<?php
/**
 * Liste des magazines qui traitent un jeu.
 *
 * @var array<string, mixed>|null $game
 * @var string $gameTitle
 * @var list<array<string, mixed>> $offeredIssues
 * @var list<array<string, mixed>> $issues
 * @var string $backUrl
 */
$offeredIssues = $offeredIssues ?? [];
$issues = $issues ?? [];
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
        </header>

        <?php if ($offeredIssues === [] && $issues === []): ?>
            <p class="hint">Aucun magazine relié pour l’instant.</p>
        <?php else: ?>
            <?php if ($offeredIssues !== []): ?>
                <section class="game-magazines-section" aria-labelledby="game-magazines-offered-heading">
                    <h2 id="game-magazines-offered-heading" class="game-detail__section-title">
                        Revues qui ont offert ce jeu
                    </h2>
                    <p class="hint">
                        <?= count($offeredIssues) ?> numéro<?= count($offeredIssues) > 1 ? 's' : '' ?>
                        où ce jeu était fourni avec la revue.
                    </p>
                    <?php
                    $magazineCoverageRows = $offeredIssues;
                    require MONCINE_ROOT . '/templates/_game_magazine_issues_grid.php';
                    ?>
                </section>
            <?php endif; ?>

            <section class="game-magazines-section" aria-labelledby="game-magazines-coverage-heading">
                <h2 id="game-magazines-coverage-heading" class="game-detail__section-title">
                    Revues qui parlent de <?= Moncine\View::escape($gameTitle) ?>
                </h2>
                <?php if ($issues === []): ?>
                    <p class="hint">Aucun test, preview ou dossier relié pour l’instant.</p>
                <?php else: ?>
                    <p class="hint">
                        <?= count($issues) ?> numéro<?= count($issues) > 1 ? 's' : '' ?>.
                    </p>
                    <?php
                    $magazineCoverageRows = $issues;
                    require MONCINE_ROOT . '/templates/_game_magazine_issues_grid.php';
                    ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>
