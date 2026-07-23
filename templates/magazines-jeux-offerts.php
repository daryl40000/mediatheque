<?php
/**
 * Liste des numéros ayant offert un jeu, regroupés par série.
 *
 * @var list<array<string, mixed>> $seriesGroups
 * @var int $mentionCount
 * @var int $seriesCount
 * @var bool $moduleAvailable
 */
$seriesGroups = $seriesGroups ?? [];
$mentionCount = (int) ($mentionCount ?? 0);
$seriesCount = (int) ($seriesCount ?? 0);
$moduleAvailable = $moduleAvailable ?? true;
?>
<section class="collection-page magazines-jeux-offerts-page">
    <header class="collection-page__header">
        <p>
            <a href="/statistiques.php" class="btn btn-secondary btn-sm">← Statistiques</a>
        </p>
        <h1>Jeux offerts</h1>
        <p class="lead">
            Numéros de magazines qui ont fourni un jeu (CD, DVD, code…).
            Regroupés par série, dans l’ordre de parution.
        </p>
        <?php if ($moduleAvailable && $mentionCount > 0): ?>
            <p class="hint">
                <?= $mentionCount ?> numéro<?= $mentionCount > 1 ? 's' : '' ?>
                · <?= $seriesCount ?> série<?= $seriesCount > 1 ? 's' : '' ?>
            </p>
        <?php endif; ?>
    </header>

    <?php if (!$moduleAvailable): ?>
        <p class="hint">Module indisponible pour le moment.</p>
    <?php elseif ($seriesGroups === []): ?>
        <p class="hint">
            Aucun jeu offert recensé pour l’instant.
            Ajoutez des sujets de catégorie <strong>Jeux offerts</strong> sur vos numéros
            (séries Jeux vidéo).
        </p>
    <?php else: ?>
        <?php foreach ($seriesGroups as $group): ?>
            <?php
            $seriesTitre = (string) ($group['series_titre'] ?? '');
            $seriesUrl = (string) ($group['series_url'] ?? '');
            $issues = $group['issues'] ?? [];
            ?>
            <section class="magazines-jeux-offerts__series" aria-label="<?= Moncine\View::escape($seriesTitre) ?>">
                <h2 class="magazines-jeux-offerts__series-title">
                    <?php if ($seriesUrl !== ''): ?>
                        <a href="<?= Moncine\View::escape($seriesUrl) ?>">
                            <?= Moncine\View::escape($seriesTitre) ?>
                        </a>
                    <?php else: ?>
                        <?= Moncine\View::escape($seriesTitre) ?>
                    <?php endif; ?>
                    <span class="tag"><?= count($issues) ?></span>
                </h2>

                <ul class="magazines-jeux-offerts__list">
                    <?php foreach ($issues as $issue): ?>
                        <?php
                        $numero = trim((string) ($issue['numero'] ?? ''));
                        $dateLabel = trim((string) ($issue['date_label'] ?? ''));
                        $gameTitre = (string) ($issue['game_titre'] ?? '');
                        $issueUrl = (string) ($issue['issue_url'] ?? '');
                        $gameUrl = (string) ($issue['game_url'] ?? '');
                        $issueLabel = $numero !== '' ? 'n°' . $numero : 'Numéro';
                        if ($dateLabel !== '') {
                            $issueLabel .= ' — ' . $dateLabel;
                        }
                        ?>
                        <li class="magazines-jeux-offerts__item">
                            <span class="magazines-jeux-offerts__issue">
                                <?php if ($issueUrl !== ''): ?>
                                    <a href="<?= Moncine\View::escape($issueUrl) ?>">
                                        <?= Moncine\View::escape($issueLabel) ?>
                                    </a>
                                <?php else: ?>
                                    <?= Moncine\View::escape($issueLabel) ?>
                                <?php endif; ?>
                            </span>
                            <span class="magazines-jeux-offerts__sep" aria-hidden="true">·</span>
                            <span class="magazines-jeux-offerts__game">
                                <?php if ($gameUrl !== ''): ?>
                                    <a href="<?= Moncine\View::escape($gameUrl) ?>">
                                        <?= Moncine\View::escape($gameTitre) ?>
                                    </a>
                                <?php else: ?>
                                    <?= Moncine\View::escape($gameTitre) ?>
                                <?php endif; ?>
                                <?php
                                // Logo Linux à côté du titre si l’info est renseignée sur le jeu.
                                $linuxBadge = (string) ($issue['linux_badge'] ?? '');
                                if ($linuxBadge !== '' || !empty($issue['tested_on_linux']) || !empty($issue['linux_not_supported'])) {
                                    $game = [
                                        'linux_badge' => $linuxBadge,
                                        'tested_on_linux' => !empty($issue['tested_on_linux']),
                                        'linux_not_supported' => !empty($issue['linux_not_supported']),
                                    ];
                                    $size = 'sm';
                                    $plain = true;
                                    require MONCINE_ROOT . '/templates/_game_linux_badge_if_set.php';
                                }
                                ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
