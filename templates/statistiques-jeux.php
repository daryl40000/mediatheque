<?php
/** @var array<string, mixed> $stats */
$s = $stats;
$mediaNav = Moncine\MediaContext::navLabels();
$collectionCount = (int) ($s['collection_count'] ?? 0);
$extensionCount = (int) ($s['extension_count'] ?? 0);
$totalInLibrary = $collectionCount + $extensionCount;
$platformBreakdown = $s['platform_breakdown'] ?? ['items' => [], 'max' => 1];
$platformItems = $platformBreakdown['items'] ?? [];
$platformMax = max(1, (int) ($platformBreakdown['max'] ?? 1));
$genreBreakdown = $s['genre_breakdown'] ?? ['items' => [], 'max' => 1];
$genreItems = $genreBreakdown['items'] ?? [];
$genreMax = max(1, (int) ($genreBreakdown['max'] ?? 1));
$decadeBreakdown = $s['decade_breakdown'] ?? ['items' => [], 'max' => 1];
$decadeItems = $decadeBreakdown['items'] ?? [];
$decadeMax = max(1, (int) ($decadeBreakdown['max'] ?? 1));
$topPlayedGames = $s['top_played_games'] ?? [];
$playtimeStatsAvailable = Moncine\GamePlaytime::isAvailable();
$playtimeMinutesTotal = (int) ($s['playtime_minutes_total'] ?? 0);
$steamPlaytimeMinutesTotal = (int) ($s['steam_playtime_minutes_total'] ?? 0);
$steamPlaytimeStatsAvailable = Moncine\GameSteamStatsRepository::isAvailable();

$collectionListUrl = Moncine\View::gamesCollectionUrl(filter: Moncine\GameListFilter::excludingExtensions());
$extensionsListUrl = Moncine\View::gamesCollectionUrl(filter: Moncine\GameListFilter::forExtensionsOnly());
$digitalListUrl = Moncine\View::gamesCollectionUrl(filter: Moncine\GameListFilter::forSupport(Moncine\GameListFilter::SUPPORT_DIGITAL));
$physicalListUrl = Moncine\View::gamesCollectionUrl(filter: Moncine\GameListFilter::forSupport(Moncine\GameListFilter::SUPPORT_PHYSICAL));
?>
<section class="stats-page">
    <h1><?= Moncine\View::escape($mediaNav['stats']) ?></h1>
    <p class="lead">
        Vue d’ensemble de votre collection de jeux vidéo : plateformes, supports démat/physique,
        genres, extensions et liens avec vos magazines (tests, previews…).
        Cliquez sur un chiffre ou un libellé pour afficher la liste correspondante.
    </p>

    <div class="stats-grid">
        <article class="stat-card stat-card--highlight">
            <p class="stat-card__value">
                <a href="<?= Moncine\View::escape($collectionListUrl) ?>" class="stat-card__link">
                    <?= $collectionCount ?>
                </a>
            </p>
            <p class="stat-card__label">Jeux en collection</p>
            <p class="stat-card__hint">
                Hors extensions<?php if ($extensionCount > 0): ?> · <?= $extensionCount ?> extension<?= $extensionCount > 1 ? 's' : '' ?> à part<?php endif; ?>
            </p>
        </article>
        <?php if ($extensionCount > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value">
                    <a href="<?= Moncine\View::escape($extensionsListUrl) ?>" class="stat-card__link">
                        <?= $extensionCount ?>
                    </a>
                </p>
                <p class="stat-card__label">Extensions en collection</p>
                <p class="stat-card__hint">DLC, season pass, add-ons…</p>
            </article>
        <?php endif; ?>
        <?php if ($collectionCount > 0 && Moncine\GameCompletionRepository::isAvailable()): ?>
            <article class="stat-card">
                <p class="stat-card__value"><?= (int) ($s['finished_count'] ?? 0) ?></p>
                <p class="stat-card__label">Jeux terminés</p>
                <?php if ((float) ($s['finished_percent'] ?? 0) > 0): ?>
                    <p class="stat-card__hint">
                        <?= Moncine\View::escape(Moncine\CollectionStats::formatPercent((float) ($s['finished_percent'] ?? 0))) ?>
                        de la collection de base
                    </p>
                <?php endif; ?>
            </article>
            <?php if ((int) ($s['completions_total'] ?? 0) > (int) ($s['finished_count'] ?? 0)): ?>
                <article class="stat-card">
                    <p class="stat-card__value"><?= (int) ($s['completions_total'] ?? 0) ?></p>
                    <p class="stat-card__label">Fins de partie enregistrées</p>
                    <p class="stat-card__hint">Reprises et nouvelles parties incluses</p>
                </article>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ((int) ($s['wishlist_count'] ?? 0) > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value"><?= (int) ($s['wishlist_count'] ?? 0) ?></p>
                <p class="stat-card__label"><?= Moncine\View::escape($mediaNav['wishlist']) ?></p>
                <p class="stat-card__hint"><a href="/jeux-envies.php">Voir la liste</a></p>
            </article>
        <?php endif; ?>
        <?php if ($collectionCount > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value">
                    <a href="<?= Moncine\View::escape($digitalListUrl) ?>" class="stat-card__link">
                        <?= (int) ($s['digital_count'] ?? 0) ?>
                    </a>
                </p>
                <p class="stat-card__label">Versions dématérialisées</p>
                <?php if ((float) ($s['digital_percent'] ?? 0) > 0): ?>
                    <p class="stat-card__hint">
                        <?= Moncine\View::escape(Moncine\CollectionStats::formatPercent((float) ($s['digital_percent'] ?? 0))) ?>
                        des jeux de base
                    </p>
                <?php endif; ?>
            </article>
            <article class="stat-card">
                <p class="stat-card__value">
                    <a href="<?= Moncine\View::escape($physicalListUrl) ?>" class="stat-card__link">
                        <?= (int) ($s['physical_count'] ?? 0) ?>
                    </a>
                </p>
                <p class="stat-card__label">Versions physiques</p>
            </article>
        <?php endif; ?>
        <?php if ((int) ($s['magazine_links_count'] ?? 0) > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value"><?= (int) ($s['magazine_links_count'] ?? 0) ?></p>
                <p class="stat-card__label">Sujets magazine reliés</p>
                <p class="stat-card__hint">Tests, previews ou interviews liés à vos jeux</p>
            </article>
        <?php endif; ?>
        <?php if ($playtimeStatsAvailable): ?>
            <article class="stat-card">
                <p class="stat-card__value stat-card__value--duration">
                    <?= Moncine\View::escape((string) ($s['playtime_duration_label'] ?? '0h 00min')) ?>
                </p>
                <p class="stat-card__label stat-card__label--with-info">
                    Temps de jeu total
                    <span class="info-tooltip" tabindex="0" aria-label="Comment est calculé le temps de jeu total">
                        <span class="info-tooltip__icon" aria-hidden="true">i</span>
                        <span class="info-tooltip__popup" role="tooltip">
                            Somme des temps Steam synchronisés et des temps saisis manuellement
                            (Battle.net, etc.) pour tous les jeux de votre collection.
                        </span>
                    </span>
                </p>
            </article>
        <?php endif; ?>
        <?php if ($steamPlaytimeStatsAvailable): ?>
            <article class="stat-card">
                <p class="stat-card__value stat-card__value--duration">
                    <?= Moncine\View::escape((string) ($s['steam_playtime_duration_label'] ?? '0h 00min')) ?>
                </p>
                <p class="stat-card__label stat-card__label--with-info">
                    Temps Steam
                    <span class="info-tooltip" tabindex="0" aria-label="Comment est calculé le temps Steam">
                        <span class="info-tooltip__icon" aria-hidden="true">i</span>
                        <span class="info-tooltip__popup" role="tooltip">
                            Somme des temps enregistrés via la synchronisation Steam uniquement.
                        </span>
                    </span>
                </p>
            </article>
        <?php endif; ?>
    </div>

    <?php if ($totalInLibrary === 0): ?>
        <p class="hint">
            Aucun jeu en collection pour l’instant.
            <a href="/ajouter-jeu.php">Ajoutez votre premier jeu</a> pour voir des statistiques.
        </p>
    <?php else: ?>
        <?php if ($topPlayedGames !== []): ?>
            <section class="stats-panel">
                <h2>Jeux les plus joués</h2>
                <p class="hint">Top 10 selon le temps de jeu total (Steam + saisie manuelle).</p>
                <ol class="stats-ranked-list">
                    <?php foreach ($topPlayedGames as $game): ?>
                        <li class="stats-ranked-list__item">
                            <a href="<?= Moncine\View::escape((string) ($game['url'] ?? '')) ?>" class="stats-ranked-list__link">
                                <?= Moncine\View::escape((string) ($game['titre'] ?? '')) ?>
                            </a>
                            <span class="tag"><?= Moncine\View::escape((string) ($game['playtime_label'] ?? '')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>

        <?php if ($platformItems !== []): ?>
            <section class="stats-panel">
                <h2>Répartition par plateforme</h2>
                <p class="hint">Jeux de base uniquement (hors extensions).</p>
                <div class="note-chart support-chart" role="img" aria-label="Répartition des jeux par plateforme">
                    <?php foreach ($platformItems as $item): ?>
                        <?php
                        $count = (int) ($item['count'] ?? 0);
                        $pctBar = $platformMax > 0 ? round(($count / $platformMax) * 100) : 0;
                        $percent = (float) ($item['percent'] ?? 0);
                        $label = (string) ($item['label'] ?? '');
                        $url = (string) ($item['url'] ?? '');
                        ?>
                        <div class="note-chart__row support-chart__row">
                            <span class="note-chart__label support-chart__label">
                                <?php if ($url !== ''): ?>
                                    <a href="<?= Moncine\View::escape($url) ?>" class="support-chart__link">
                                        <?= Moncine\View::escape($label) ?>
                                    </a>
                                <?php else: ?>
                                    <?= Moncine\View::escape($label) ?>
                                <?php endif; ?>
                            </span>
                            <span class="note-chart__bar-wrap">
                                <span class="note-chart__bar support-chart__bar" style="width: <?= max(2, $pctBar) ?>%;"
                                      title="<?= $count ?> jeu<?= $count > 1 ? 'x' : '' ?>"></span>
                            </span>
                            <span class="note-chart__count">
                                <?php if ($url !== ''): ?>
                                    <a href="<?= Moncine\View::escape($url) ?>" class="support-chart__link"><?= $count ?></a>
                                <?php else: ?>
                                    <?= $count ?>
                                <?php endif; ?>
                                <span class="support-chart__pct">(<?= Moncine\View::escape(Moncine\CollectionStats::formatPercent($percent)) ?>)</span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($genreItems !== []): ?>
            <section class="stats-panel">
                <h2>Genres les plus représentés</h2>
                <p class="hint">Un jeu peut compter dans plusieurs genres.</p>
                <div class="note-chart support-chart" role="img" aria-label="Genres les plus fréquents dans la collection jeux">
                    <?php foreach ($genreItems as $item): ?>
                        <?php
                        $count = (int) ($item['count'] ?? 0);
                        $pctBar = $genreMax > 0 ? round(($count / $genreMax) * 100) : 0;
                        $percent = (float) ($item['percent'] ?? 0);
                        $label = (string) ($item['label'] ?? '');
                        $url = (string) ($item['url'] ?? '');
                        ?>
                        <div class="note-chart__row support-chart__row">
                            <span class="note-chart__label support-chart__label">
                                <?php if ($url !== ''): ?>
                                    <a href="<?= Moncine\View::escape($url) ?>" class="support-chart__link">
                                        <?= Moncine\View::escape($label) ?>
                                    </a>
                                <?php else: ?>
                                    <?= Moncine\View::escape($label) ?>
                                <?php endif; ?>
                            </span>
                            <span class="note-chart__bar-wrap">
                                <span class="note-chart__bar support-chart__bar" style="width: <?= max(2, $pctBar) ?>%;"
                                      title="<?= $count ?> jeu<?= $count > 1 ? 'x' : '' ?>"></span>
                            </span>
                            <span class="note-chart__count">
                                <?php if ($url !== ''): ?>
                                    <a href="<?= Moncine\View::escape($url) ?>" class="support-chart__link"><?= $count ?></a>
                                <?php else: ?>
                                    <?= $count ?>
                                <?php endif; ?>
                                <span class="support-chart__pct">(<?= Moncine\View::escape(Moncine\CollectionStats::formatPercent($percent)) ?>)</span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($decadeItems !== []): ?>
            <section class="stats-panel">
                <h2>Jeux par décennie de sortie</h2>
                <div class="note-chart support-chart" role="img" aria-label="Répartition des jeux par décennie de sortie">
                    <?php foreach ($decadeItems as $item): ?>
                        <?php
                        $count = (int) ($item['count'] ?? 0);
                        $pctBar = $decadeMax > 0 ? round(($count / $decadeMax) * 100) : 0;
                        $percent = (float) ($item['percent'] ?? 0);
                        $label = (string) ($item['label'] ?? '');
                        $url = (string) ($item['url'] ?? '');
                        ?>
                        <div class="note-chart__row support-chart__row">
                            <span class="note-chart__label support-chart__label">
                                <?php if ($url !== ''): ?>
                                    <a href="<?= Moncine\View::escape($url) ?>" class="support-chart__link">
                                        <?= Moncine\View::escape($label) ?>
                                    </a>
                                <?php else: ?>
                                    <?= Moncine\View::escape($label) ?>
                                <?php endif; ?>
                            </span>
                            <span class="note-chart__bar-wrap">
                                <span class="note-chart__bar support-chart__bar" style="width: <?= max(2, $pctBar) ?>%;"
                                      title="<?= $count ?> jeu<?= $count > 1 ? 'x' : '' ?>"></span>
                            </span>
                            <span class="note-chart__count">
                                <?php if ($url !== ''): ?>
                                    <a href="<?= Moncine\View::escape($url) ?>" class="support-chart__link"><?= $count ?></a>
                                <?php else: ?>
                                    <?= $count ?>
                                <?php endif; ?>
                                <span class="support-chart__pct">(<?= Moncine\View::escape(Moncine\CollectionStats::formatPercent($percent)) ?>)</span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>
