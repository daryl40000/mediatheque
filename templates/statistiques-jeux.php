<?php
/** @var array<string, mixed> $stats */
$s = $stats;
$mediaNav = Moncine\MediaContext::navLabels();
$collectionCount = (int) ($s['collection_count'] ?? 0);
$platformBreakdown = $s['platform_breakdown'] ?? ['items' => [], 'max' => 1];
$platformItems = $platformBreakdown['items'] ?? [];
$platformMax = max(1, (int) ($platformBreakdown['max'] ?? 1));
$genreBreakdown = $s['genre_breakdown'] ?? ['items' => [], 'max' => 1];
$genreItems = $genreBreakdown['items'] ?? [];
$genreMax = max(1, (int) ($genreBreakdown['max'] ?? 1));
$decadeBreakdown = $s['decade_breakdown'] ?? ['items' => [], 'max' => 1];
$decadeItems = $decadeBreakdown['items'] ?? [];
$decadeMax = max(1, (int) ($decadeBreakdown['max'] ?? 1));
?>
<section class="stats-page">
    <h1><?= Moncine\View::escape($mediaNav['stats']) ?></h1>
    <p class="lead">
        Vue d’ensemble de votre collection de jeux vidéo : plateformes, supports démat/physique,
        genres et liens avec vos magazines (tests, previews…).
    </p>

    <div class="stats-grid">
        <article class="stat-card stat-card--highlight">
            <p class="stat-card__value"><?= $collectionCount ?></p>
            <p class="stat-card__label">Jeux en collection</p>
            <p class="stat-card__hint"><a href="/jeux.php">Voir la liste</a></p>
        </article>
        <?php if ((int) ($s['wishlist_count'] ?? 0) > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value"><?= (int) ($s['wishlist_count'] ?? 0) ?></p>
                <p class="stat-card__label"><?= Moncine\View::escape($mediaNav['wishlist']) ?></p>
                <p class="stat-card__hint"><a href="/jeux-envies.php">Voir la liste</a></p>
            </article>
        <?php endif; ?>
        <?php if ($collectionCount > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value"><?= (int) ($s['digital_count'] ?? 0) ?></p>
                <p class="stat-card__label">Versions dématérialisées</p>
                <?php if ((float) ($s['digital_percent'] ?? 0) > 0): ?>
                    <p class="stat-card__hint">
                        <?= Moncine\View::escape(Moncine\CollectionStats::formatPercent((float) ($s['digital_percent'] ?? 0))) ?>
                        de la collection
                    </p>
                <?php endif; ?>
            </article>
            <article class="stat-card">
                <p class="stat-card__value"><?= (int) ($s['physical_count'] ?? 0) ?></p>
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
    </div>

    <?php if ($collectionCount === 0): ?>
        <p class="hint">
            Aucun jeu en collection pour l’instant.
            <a href="/ajouter-jeu.php">Ajoutez votre premier jeu</a> pour voir des statistiques.
        </p>
    <?php else: ?>
        <?php if ($platformItems !== []): ?>
            <section class="stats-panel">
                <h2>Répartition par plateforme</h2>
                <div class="note-chart support-chart" role="img" aria-label="Répartition des jeux par plateforme">
                    <?php foreach ($platformItems as $item): ?>
                        <?php
                        $count = (int) ($item['count'] ?? 0);
                        $pctBar = $platformMax > 0 ? round(($count / $platformMax) * 100) : 0;
                        $percent = (float) ($item['percent'] ?? 0);
                        ?>
                        <div class="note-chart__row support-chart__row">
                            <span class="note-chart__label support-chart__label">
                                <?= Moncine\View::escape((string) ($item['label'] ?? '')) ?>
                            </span>
                            <span class="note-chart__bar-wrap">
                                <span class="note-chart__bar support-chart__bar" style="width: <?= max(2, $pctBar) ?>%;"
                                      title="<?= $count ?> jeu<?= $count > 1 ? 'x' : '' ?>"></span>
                            </span>
                            <span class="note-chart__count">
                                <?= $count ?>
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
                <div class="note-chart support-chart" role="img" aria-label="Genres les plus fréquents dans la collection jeux">
                    <?php foreach ($genreItems as $item): ?>
                        <?php
                        $count = (int) ($item['count'] ?? 0);
                        $pctBar = $genreMax > 0 ? round(($count / $genreMax) * 100) : 0;
                        $percent = (float) ($item['percent'] ?? 0);
                        ?>
                        <div class="note-chart__row support-chart__row">
                            <span class="note-chart__label support-chart__label">
                                <?= Moncine\View::escape((string) ($item['label'] ?? '')) ?>
                            </span>
                            <span class="note-chart__bar-wrap">
                                <span class="note-chart__bar support-chart__bar" style="width: <?= max(2, $pctBar) ?>%;"
                                      title="<?= $count ?> jeu<?= $count > 1 ? 'x' : '' ?>"></span>
                            </span>
                            <span class="note-chart__count">
                                <?= $count ?>
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
                        ?>
                        <div class="note-chart__row support-chart__row">
                            <span class="note-chart__label support-chart__label">
                                <?= Moncine\View::escape((string) ($item['label'] ?? '')) ?>
                            </span>
                            <span class="note-chart__bar-wrap">
                                <span class="note-chart__bar support-chart__bar" style="width: <?= max(2, $pctBar) ?>%;"
                                      title="<?= $count ?> jeu<?= $count > 1 ? 'x' : '' ?>"></span>
                            </span>
                            <span class="note-chart__count">
                                <?= $count ?>
                                <span class="support-chart__pct">(<?= Moncine\View::escape(Moncine\CollectionStats::formatPercent($percent)) ?>)</span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>
