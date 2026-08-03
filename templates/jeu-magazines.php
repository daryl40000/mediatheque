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
$scoreStats = Moncine\MagazineGameLink::averageScorePercent($issues);
$averageScore = $scoreStats['average'];
$scoredCount = (int) $scoreStats['count'];
?>
<section class="collection-page game-detail-page">
    <?php if ($game === null): ?>
        <h1>Fiche introuvable</h1>
        <p class="hint">Cette fiche n’existe pas ou n’est plus dans le catalogue.</p>
        <p><a href="/jeux.php" class="btn btn-secondary">← Mes jeux</a></p>
    <?php else: ?>
        <header class="collection-page__header">
            <p>
                <a href="<?= Moncine\View::escape($backUrl) ?>" class="btn btn-secondary btn-sm">← <?= Moncine\View::escape($gameTitle) ?></a>
            </p>
            <h1>Magazines</h1>
            <?php if ($averageScore !== null): ?>
                <p class="game-magazines-average" aria-label="Note moyenne presse">
                    <span class="game-magazines-average__value">
                        <?= Moncine\View::escape(Moncine\MagazineRatingScale::formatNumber((float) $averageScore)) ?>/100
                    </span>
                    <span class="game-magazines-average__label">
                        moyenne presse
                        (<?= $scoredCount ?> test<?= $scoredCount > 1 ? 's' : '' ?>)
                    </span>
                </p>
            <?php endif; ?>
        </header>

        <?php if ($offeredIssues === [] && $issues === []): ?>
            <p class="hint">Aucun magazine relié pour l’instant.</p>
        <?php else: ?>
            <?php if ($offeredIssues !== []): ?>
                <section class="game-magazines-section" aria-labelledby="game-magazines-offered-heading">
                    <h2 id="game-magazines-offered-heading" class="game-detail__section-title">
                        Revues qui ont offert ce titre
                    </h2>
                    <p class="hint">
                        <?= count($offeredIssues) ?> numéro<?= count($offeredIssues) > 1 ? 's' : '' ?>
                        où ce titre était fourni avec la revue.
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
                        Le bouton PDF ouvre le fichier
                        (à la bonne page si elle est renseignée sur le sujet).
                        <?php if ($averageScore !== null): ?>
                            Note moyenne des tests :
                            <strong><?= Moncine\View::escape(Moncine\MagazineRatingScale::formatNumber((float) $averageScore)) ?>/100</strong>
                            sur <?= $scoredCount ?> note<?= $scoredCount > 1 ? 's' : '' ?>.
                        <?php endif; ?>
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
