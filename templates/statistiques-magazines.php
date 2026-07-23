<?php
/**
 * Statistiques magazines (collection + classements sur une période).
 *
 * @var int $seriesCount
 * @var int $issueCount
 * @var int $wishlistCount
 * @var int $pdfCount
 * @var string $pdfStorageLabel
 * @var array<string, mixed> $periodStats
 */
$mediaNav = Moncine\MediaContext::navLabels();
$periodStats = $periodStats ?? [
    'active' => false,
    'from_year' => 0,
    'to_year' => 0,
    'year_choices' => [],
    'games_most' => [],
    'games_least' => [],
    'series_most_tests' => [],
    'series_most_previews' => [],
];
$yearChoices = $periodStats['year_choices'] ?? [];
$fromYear = (int) ($periodStats['from_year'] ?? 0);
$toYear = (int) ($periodStats['to_year'] ?? 0);
$periodActive = !empty($periodStats['active']);
$gamesMost = $periodStats['games_most'] ?? [];
$gamesLeast = $periodStats['games_least'] ?? [];
$seriesMostTests = $periodStats['series_most_tests'] ?? [];
$seriesMostPreviews = $periodStats['series_most_previews'] ?? [];
?>
<section class="stats-page">
    <h1><?= Moncine\View::escape($mediaNav['stats']) ?></h1>
    <p class="lead">Vue d’ensemble de votre collection de magazines.</p>

    <?php if (Moncine\MagazineJeuxOffertsList::isAvailable()): ?>
        <p class="stats-page__actions">
            <a href="<?= Moncine\View::escape(Moncine\View::magazinesJeuxOffertsUrl()) ?>" class="btn btn-secondary">
                Jeux offerts
            </a>
        </p>
    <?php endif; ?>

    <div class="stats-grid">
        <article class="stat-card stat-card--highlight">
            <p class="stat-card__value"><?= (int) $seriesCount ?></p>
            <p class="stat-card__label">Séries en collection</p>
            <p class="stat-card__hint"><a href="/magazines.php">Voir la liste</a></p>
        </article>
        <article class="stat-card">
            <p class="stat-card__value"><?= (int) $issueCount ?></p>
            <p class="stat-card__label">Numéros possédés</p>
        </article>
        <article class="stat-card">
            <p class="stat-card__value"><?= (int) $pdfCount ?></p>
            <p class="stat-card__label">PDF possédés</p>
            <p class="stat-card__hint">Numéros avec fichier importé</p>
        </article>
        <article class="stat-card">
            <p class="stat-card__value"><?= Moncine\View::escape($pdfStorageLabel) ?></p>
            <p class="stat-card__label">Espace disque (PDF)</p>
            <p class="stat-card__hint">Taille enregistrée à l’import</p>
        </article>
        <?php if ((int) $wishlistCount > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value"><?= (int) $wishlistCount ?></p>
                <p class="stat-card__label"><?= Moncine\View::escape($mediaNav['wishlist']) ?></p>
                <p class="stat-card__hint"><a href="/magazines-envies.php">Voir la liste</a></p>
            </article>
        <?php endif; ?>
    </div>

    <?php if (Moncine\MagazinePeriodStats::isAvailable()): ?>
        <section class="stats-panel magazine-period-stats" aria-labelledby="magazine-period-stats-heading">
            <h2 id="magazine-period-stats-heading">Statistiques sur une période</h2>
            <p class="hint">
                Choisissez les années de <strong>parution des numéros</strong> pour voir les jeux
                les plus (ou moins) évoqués, et les magazines avec le plus de tests ou de previews.
            </p>

            <form method="get" action="/statistiques.php" class="magazine-period-stats__form import-form">
                <div class="magazine-period-stats__fields">
                    <div>
                        <label for="period_from">De l’année</label>
                        <select name="period_from" id="period_from" required>
                            <option value="">—</option>
                            <?php foreach ($yearChoices as $year): ?>
                                <option value="<?= (int) $year ?>"<?= $fromYear === (int) $year ? ' selected' : '' ?>>
                                    <?= (int) $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="period_to">à l’année</label>
                        <select name="period_to" id="period_to" required>
                            <option value="">—</option>
                            <?php foreach ($yearChoices as $year): ?>
                                <option value="<?= (int) $year ?>"<?= $toYear === (int) $year ? ' selected' : '' ?>>
                                    <?= (int) $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="magazine-period-stats__actions">
                        <button type="submit" class="btn btn-primary">Afficher</button>
                        <?php if ($periodActive): ?>
                            <a href="/statistiques.php" class="btn btn-secondary">Réinitialiser</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?php if ($periodActive): ?>
                <p class="magazine-period-stats__period-label">
                    Période sélectionnée :
                    <?php if ($fromYear === $toYear): ?>
                        <strong><?= $fromYear ?></strong>
                    <?php else: ?>
                        <strong><?= $fromYear ?></strong> → <strong><?= $toYear ?></strong>
                    <?php endif; ?>
                </p>

                <div class="magazine-period-stats__grid">
                    <section class="magazine-period-stats__block" aria-labelledby="mag-stats-games-most">
                        <h3 id="mag-stats-games-most">Les 10 jeux dont on a le plus parlé</h3>
                        <?php if ($gamesMost === []): ?>
                            <p class="hint">Aucun jeu relié à un sujet sur cette période.</p>
                        <?php else: ?>
                            <ol class="stats-ranked-list">
                                <?php foreach ($gamesMost as $game): ?>
                                    <li class="stats-ranked-list__item">
                                        <a href="<?= Moncine\View::escape((string) ($game['url'] ?? '')) ?>"
                                           class="stats-ranked-list__link">
                                            <?= Moncine\View::escape((string) ($game['titre'] ?? '')) ?>
                                        </a>
                                        <span class="tag">
                                            <?= (int) ($game['subject_count'] ?? 0) ?>
                                            mention<?= (int) ($game['subject_count'] ?? 0) > 1 ? 's' : '' ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </section>

                    <section class="magazine-period-stats__block" aria-labelledby="mag-stats-games-least">
                        <h3 id="mag-stats-games-least">Les 10 jeux dont on a le moins parlé</h3>
                        <p class="hint">Parmi les jeux évoqués au moins une fois sur la période.</p>
                        <?php if ($gamesLeast === []): ?>
                            <p class="hint">Aucun jeu relié à un sujet sur cette période.</p>
                        <?php else: ?>
                            <ol class="stats-ranked-list">
                                <?php foreach ($gamesLeast as $game): ?>
                                    <li class="stats-ranked-list__item">
                                        <a href="<?= Moncine\View::escape((string) ($game['url'] ?? '')) ?>"
                                           class="stats-ranked-list__link">
                                            <?= Moncine\View::escape((string) ($game['titre'] ?? '')) ?>
                                        </a>
                                        <span class="tag">
                                            <?= (int) ($game['subject_count'] ?? 0) ?>
                                            mention<?= (int) ($game['subject_count'] ?? 0) > 1 ? 's' : '' ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </section>

                    <section class="magazine-period-stats__block" aria-labelledby="mag-stats-tests">
                        <h3 id="mag-stats-tests">Les 5 magazines avec le plus de tests</h3>
                        <?php if ($seriesMostTests === []): ?>
                            <p class="hint">Aucun test recensé sur cette période.</p>
                        <?php else: ?>
                            <ol class="stats-ranked-list">
                                <?php foreach ($seriesMostTests as $series): ?>
                                    <li class="stats-ranked-list__item">
                                        <a href="<?= Moncine\View::escape((string) ($series['url'] ?? '')) ?>"
                                           class="stats-ranked-list__link">
                                            <?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?>
                                        </a>
                                        <span class="tag">
                                            <?= (int) ($series['subject_count'] ?? 0) ?>
                                            test<?= (int) ($series['subject_count'] ?? 0) > 1 ? 's' : '' ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </section>

                    <section class="magazine-period-stats__block" aria-labelledby="mag-stats-previews">
                        <h3 id="mag-stats-previews">Les 5 magazines avec le plus de previews</h3>
                        <?php if ($seriesMostPreviews === []): ?>
                            <p class="hint">Aucune preview recensée sur cette période.</p>
                        <?php else: ?>
                            <ol class="stats-ranked-list">
                                <?php foreach ($seriesMostPreviews as $series): ?>
                                    <li class="stats-ranked-list__item">
                                        <a href="<?= Moncine\View::escape((string) ($series['url'] ?? '')) ?>"
                                           class="stats-ranked-list__link">
                                            <?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?>
                                        </a>
                                        <span class="tag">
                                            <?= (int) ($series['subject_count'] ?? 0) ?>
                                            preview<?= (int) ($series['subject_count'] ?? 0) > 1 ? 's' : '' ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </section>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
