<?php
/**
 * @var array<string, mixed> $stats
 */
$s = $stats;
$currentYear = (int) ($s['current_year'] ?? (int) date('Y'));
$noteDist = $s['note_distribution'] ?? [];
$noteMax = max(1, (int) ($s['note_distribution_max'] ?? 1));
$viewsByYear = $s['views_by_year'] ?? [];
$yearChartMax = 1;
foreach ($viewsByYear as $row) {
    $yearChartMax = max($yearChartMax, (int) ($row['viewings'] ?? 0));
}
$filmsVusTotal = (int) ($s['films_vus_total'] ?? 0);
$visionsTotal = (int) ($s['visions_total'] ?? 0);
$filmsVusYear = (int) ($s['films_vus_year'] ?? 0);
$visionsYear = (int) ($s['visions_year'] ?? 0);
$rewatchesTotal = max(0, $visionsTotal - $filmsVusTotal);
$rewatchesYear = max(0, $visionsYear - $filmsVusYear);
$supportBreakdown = $s['support_breakdown'] ?? ['items' => [], 'max' => 1, 'unknown_count' => 0];
$supportItems = $supportBreakdown['items'] ?? [];
$supportMax = max(1, (int) ($supportBreakdown['max'] ?? 1));
$totalFilms = (int) ($s['total_films'] ?? 0);
?>
<section class="stats-page">
    <h1>Statistiques</h1>
    <p class="lead">
        Vue d’ensemble de votre dvdthèque : films possédés, envies, visions, notes,
        types de support (DVD, Blu-ray…) et évolution année par année.
    </p>

    <div class="stats-grid">
        <article class="stat-card stat-card--highlight">
            <p class="stat-card__value"><?= (int) ($s['total_films'] ?? 0) ?></p>
            <p class="stat-card__label">Films possédés</p>
        </article>
        <?php if (!empty($s['has_wishlist'])): ?>
            <article class="stat-card">
                <p class="stat-card__value"><?= (int) ($s['wishlist_count'] ?? 0) ?></p>
                <p class="stat-card__label"><?= Moncine\View::escape(Moncine\LibraryStatut::label(Moncine\LibraryStatut::WISHLIST)) ?></p>
                <p class="stat-card__hint">
                    <a href="/souhaits.php">Voir la liste</a>
                </p>
            </article>
        <?php endif; ?>
        <article class="stat-card">
            <p class="stat-card__value"><?= $filmsVusTotal ?></p>
            <p class="stat-card__label">Films différents déjà vus</p>
            <?php if ($visionsTotal > $filmsVusTotal): ?>
                <p class="stat-card__hint">
                    <?= $visionsTotal ?> soirées enregistrées
                    <?php if ($rewatchesTotal > 0): ?>
                        (<?= $rewatchesTotal ?> re-vision<?= $rewatchesTotal > 1 ? 's' : '' ?>)
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </article>
        <?php if ($visionsTotal > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value stat-card__value--duration">
                    <?= Moncine\View::escape((string) ($s['viewing_duration_label'] ?? '0h 00min')) ?>
                </p>
                <p class="stat-card__label stat-card__label--with-info">
                    Temps de vision cumulé
                    <span class="info-tooltip" tabindex="0" aria-label="Comment est calculé le temps de vision cumulé">
                        <span class="info-tooltip__icon" aria-hidden="true">i</span>
                        <span class="info-tooltip__popup" role="tooltip">
                            Depuis le début, toutes visions comptées (durée de chaque film × nombre de visionnages).
                            Affichage jours, heures, minutes (ex. 2h 30min ou 3j 5h 30min).
                        </span>
                    </span>
                </p>
            </article>
        <?php endif; ?>
        <article class="stat-card">
            <p class="stat-card__value"><?= $filmsVusYear ?></p>
            <p class="stat-card__label">Nouveaux films vus en <?= $currentYear ?></p>
            <?php if ($visionsYear > $filmsVusYear): ?>
                <p class="stat-card__hint">
                    <?= $visionsYear ?> soirées cette année
                    <?php if ($rewatchesYear > 0): ?>
                        (<?= $rewatchesYear ?> re-vision<?= $rewatchesYear > 1 ? 's' : '' ?>)
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </article>
        <article class="stat-card">
            <p class="stat-card__value"><?= Moncine\View::escape(Moncine\CollectionStats::formatPercent((float) ($s['percent_seen'] ?? 0))) ?></p>
            <p class="stat-card__label">Films déjà vus</p>
            <p class="stat-card__hint">
                <?= (int) ($s['films_jamais_vus'] ?? 0) ?> film<?= (int) ($s['films_jamais_vus'] ?? 0) > 1 ? 's' : '' ?>
                jamais visionné<?= (int) ($s['films_jamais_vus'] ?? 0) > 1 ? 's' : '' ?>
            </p>
        </article>
    </div>

    <?php if ($totalFilms > 0 && $supportItems !== []): ?>
        <section class="stats-panel">
            <h2>Support physique</h2>
            <p class="hint">
                Répartition de vos films par type de disque (DVD, Blu-ray, Blu-ray 4K).
                <?php if ((int) ($supportBreakdown['unknown_count'] ?? 0) > 0): ?>
                    Complétez le champ <strong>Support</strong> sur les fiches sans type indiqué.
                <?php endif; ?>
            </p>
            <div class="support-chart note-chart" role="img"
                 aria-label="Répartition par type de support physique">
                <?php foreach ($supportItems as $item):
                    $count = (int) ($item['count'] ?? 0);
                    $pctBar = $supportMax > 0 ? round(($count / $supportMax) * 100) : 0;
                    $label = (string) ($item['label'] ?? '');
                    $url = (string) ($item['url'] ?? '');
                    $percent = (float) ($item['percent'] ?? 0);
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
                                  title="<?= $count ?> film<?= $count > 1 ? 's' : '' ?>"></span>
                        </span>
                        <span class="note-chart__count">
                            <?= $count ?>
                            <span class="support-chart__pct">(<?= Moncine\View::escape(Moncine\CollectionStats::formatPercent($percent)) ?>)</span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="hint">
                <a href="/support.php">Voir les films par support</a>
            </p>
        </section>
    <?php endif; ?>

    <section class="stats-panel">
        <h2>Notes</h2>
        <?php if ((int) ($s['notes_count'] ?? 0) === 0): ?>
            <p class="hint">
                Aucune note enregistrée pour l’instant. Notez vos films lors d’une vision
                (fiche film ou import avec la colonne <strong>Note</strong>).
            </p>
        <?php else: ?>
            <div class="stats-notes-summary">
                <div class="stat-inline">
                    <span class="stat-inline__value"><?= Moncine\View::escape(Moncine\CollectionStats::formatAverage($s['note_moyenne_visions'] ?? null)) ?></span>
                    <span class="stat-inline__label">Moyenne de toutes les notes</span>
                    <span class="stat-inline__hint"><?= (int) ($s['notes_count'] ?? 0) ?> note<?= (int) ($s['notes_count'] ?? 0) > 1 ? 's' : '' ?></span>
                </div>
                <div class="stat-inline">
                    <span class="stat-inline__value"><?= Moncine\View::escape(Moncine\CollectionStats::formatAverage($s['note_moyenne_films'] ?? null)) ?></span>
                    <span class="stat-inline__label">Moyenne par film</span>
                    <span class="stat-inline__hint">meilleure note de chaque fiche</span>
                </div>
                <?php if ((int) ($s['visions_sans_note'] ?? 0) > 0): ?>
                    <p class="hint stat-inline__aside">
                        <?= (int) ($s['visions_sans_note'] ?? 0) ?> vision<?= (int) ($s['visions_sans_note'] ?? 0) > 1 ? 's' : '' ?>
                        sans note (date seule).
                    </p>
                <?php endif; ?>
            </div>

            <h3 class="stats-subtitle">Répartition des notes (1 à 10)</h3>
            <div class="note-chart" role="img"
                 aria-label="Graphique de répartition des notes de 1 à 10 sur 10">
                <?php for ($n = 1; $n <= 10; $n++):
                    $count = (int) ($noteDist[$n] ?? 0);
                    $pct = $noteMax > 0 ? round(($count / $noteMax) * 100) : 0;
                    ?>
                    <div class="note-chart__row">
                        <span class="note-chart__label"><?= $n ?></span>
                        <span class="note-chart__bar-wrap">
                            <span class="note-chart__bar" style="width: <?= max(2, $pct) ?>%;"
                                  title="<?= $count ?> note<?= $count > 1 ? 's' : '' ?>"></span>
                        </span>
                        <span class="note-chart__count"><?= $count ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($viewsByYear !== []): ?>
        <section class="stats-panel">
            <h2>Activité par année</h2>
            <p class="hint">
                Hauteur des barres = soirées enregistrées. Le chiffre sous l’année = films différents vus
                (un même film revu compte une seule fois).
            </p>
            <div class="year-chart">
                <?php foreach ($viewsByYear as $row):
                    $visions = (int) ($row['viewings'] ?? 0);
                    $films = (int) ($row['films'] ?? 0);
                    $pct = $yearChartMax > 0 ? round(($visions / $yearChartMax) * 100) : 0;
                    ?>
                    <div class="year-chart__item">
                        <span class="year-chart__bar-wrap">
                            <span class="year-chart__bar" style="height: <?= max(4, $pct) ?>%;"
                                  title="<?= $visions ?> visionnage<?= $visions > 1 ? 's' : '' ?>, <?= $films ?> film<?= $films > 1 ? 's' : '' ?>"></span>
                        </span>
                        <span class="year-chart__year"><?= (int) ($row['year'] ?? 0) ?></span>
                        <span class="year-chart__meta" title="<?= $visions ?> soirée<?= $visions > 1 ? 's' : '' ?>, <?= $films ?> film<?= $films > 1 ? 's' : '' ?>">
                            <?= $films ?><?php if ($visions > $films): ?><span class="year-chart__meta-extra">/<?= $visions ?></span><?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php
    $topRated = $s['top_rated'] ?? [];
    if ($topRated !== []):
        ?>
        <section class="stats-panel">
            <h2>Films les mieux notés</h2>
            <ol class="stats-ranked-list">
                <?php foreach ($topRated as $film): ?>
                    <li>
                        <a href="/film.php?id=<?= (int) $film['id'] ?>" class="stats-ranked-list__link">
                            <?= Moncine\View::escape((string) $film['titre']) ?>
                        </a>
                        <?php if (trim((string) ($film['realisateur'] ?? '')) !== ''): ?>
                            <span class="stats-ranked-list__meta">— <?= Moncine\View::escape((string) $film['realisateur']) ?></span>
                        <?php endif; ?>
                        <span class="tag tag--note"><?= (int) ($film['best_note'] ?? 0) ?>/10</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php
    $rewatched = $s['most_rewatched'] ?? [];
    if ($rewatched !== []):
        ?>
        <section class="stats-panel">
            <h2>Films revus le plus souvent</h2>
            <ol class="stats-ranked-list">
                <?php foreach ($rewatched as $film): ?>
                    <li>
                        <a href="/film.php?id=<?= (int) $film['id'] ?>" class="stats-ranked-list__link">
                            <?= Moncine\View::escape((string) $film['titre']) ?>
                        </a>
                        <span class="tag"><?= (int) ($film['view_count'] ?? 0) ?> fois</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php if ((int) ($s['total_films'] ?? 0) === 0): ?>
        <p class="alert alert-warning">
            Vous n’avez aucun film enregistré.
            <a href="/import.php">Importez vos films</a> pour voir des statistiques.
        </p>
    <?php elseif ((int) ($s['visions_total'] ?? 0) === 0): ?>
        <p class="hint">
            Marquez des films comme vus depuis leur fiche pour alimenter ces statistiques.
        </p>
    <?php endif; ?>
</section>
