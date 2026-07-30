<?php
/**
 * Statistiques livres.
 *
 * @var array<string, mixed> $stats
 */
$s = $stats;
$mediaNav = Moncine\MediaContext::navLabels();
$currentYear = (int) ($s['current_year'] ?? (int) date('Y'));
$noteDist = $s['ressenti_distribution'] ?? [];
$noteMax = max(1, (int) ($s['ressenti_distribution_max'] ?? 1));
$ressentiKeys = array_reverse(Moncine\RessentiNote::orderedKeys());
$readsByYear = $s['reads_by_year'] ?? [];
$yearChartMax = 1;
foreach ($readsByYear as $row) {
    $yearChartMax = max($yearChartMax, (int) ($row['readings'] ?? 0));
}
$booksReadTotal = (int) ($s['books_read_total'] ?? 0);
$readingsTotal = (int) ($s['readings_total'] ?? 0);
$booksReadYear = (int) ($s['books_read_year'] ?? 0);
$readingsYear = (int) ($s['readings_year'] ?? 0);
$rereadsTotal = max(0, $readingsTotal - $booksReadTotal);
$rereadsYear = max(0, $readingsYear - $booksReadYear);
$totalBooks = (int) ($s['total_books'] ?? 0);
$supportBreakdown = $s['support_breakdown'] ?? ['items' => [], 'max' => 1, 'unknown_count' => 0];
$supportItems = $supportBreakdown['items'] ?? [];
$supportMax = max(1, (int) ($supportBreakdown['max'] ?? 1));
$categoryBreakdown = $s['category_breakdown'] ?? ['items' => [], 'max' => 1];
$categoryItems = $categoryBreakdown['items'] ?? [];
$categoryMax = max(1, (int) ($categoryBreakdown['max'] ?? 1));
$pagesRead = (int) ($s['pages_read_total'] ?? 0);
?>
<section class="stats-page">
    <h1><?= Moncine\View::escape($mediaNav['stats']) ?></h1>
    <p class="lead">
        Vue d’ensemble de votre bibliothèque : livres possédés, lectures, ressentis,
        catégories et évolution année par année.
    </p>

    <nav class="ui-pill-nav" aria-label="Accès rapides livres">
        <a href="/livres.php" class="ui-pill"><?= Moncine\View::escape($mediaNav['collection']) ?></a>
        <a href="/livres-envies.php" class="ui-pill"><?= Moncine\View::escape($mediaNav['wishlist']) ?></a>
        <a href="/sagas-livres.php" class="ui-pill">Sagas</a>
    </nav>

    <div class="stats-grid">
        <article class="stat-card stat-card--highlight">
            <p class="stat-card__value">
                <a href="/livres.php" class="stat-card__link"><?= $totalBooks ?></a>
            </p>
            <p class="stat-card__label">Livres en collection</p>
        </article>

        <?php if ((int) ($s['wishlist_count'] ?? 0) > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value">
                    <a href="/livres-envies.php" class="stat-card__link"><?= (int) $s['wishlist_count'] ?></a>
                </p>
                <p class="stat-card__label"><?= Moncine\View::escape(Moncine\LibraryStatut::label(Moncine\LibraryStatut::WISHLIST)) ?></p>
            </article>
        <?php endif; ?>

        <?php if ((int) ($s['saga_count'] ?? 0) > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value">
                    <a href="/sagas-livres.php" class="stat-card__link"><?= (int) $s['saga_count'] ?></a>
                </p>
                <p class="stat-card__label">Saga<?= (int) $s['saga_count'] > 1 ? 's' : '' ?></p>
            </article>
        <?php endif; ?>

        <article class="stat-card">
            <p class="stat-card__value"><?= $booksReadTotal ?></p>
            <p class="stat-card__label">Livres différents déjà lus</p>
            <?php if ($readingsTotal > $booksReadTotal): ?>
                <p class="stat-card__hint">
                    <?= $readingsTotal ?> lecture<?= $readingsTotal > 1 ? 's' : '' ?> enregistrée<?= $readingsTotal > 1 ? 's' : '' ?>
                    <?php if ($rereadsTotal > 0): ?>
                        (<?= $rereadsTotal ?> relecture<?= $rereadsTotal > 1 ? 's' : '' ?>)
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </article>

        <?php if ($pagesRead > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value"><?= number_format($pagesRead, 0, ',', ' ') ?></p>
                <p class="stat-card__label">Pages lues (cumul)</p>
                <p class="stat-card__hint">Chaque lecture compte les pages du livre</p>
            </article>
        <?php endif; ?>

        <article class="stat-card">
            <p class="stat-card__value"><?= $booksReadYear ?></p>
            <p class="stat-card__label">Nouveaux livres lus en <?= $currentYear ?></p>
            <?php if ($readingsYear > $booksReadYear): ?>
                <p class="stat-card__hint">
                    <?= $readingsYear ?> lecture<?= $readingsYear > 1 ? 's' : '' ?> cette année
                    <?php if ($rereadsYear > 0): ?>
                        (<?= $rereadsYear ?> relecture<?= $rereadsYear > 1 ? 's' : '' ?>)
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </article>

        <article class="stat-card">
            <p class="stat-card__value"><?= Moncine\View::escape(Moncine\CollectionStats::formatPercent((float) ($s['percent_read'] ?? 0))) ?></p>
            <p class="stat-card__label">Livres déjà lus</p>
            <p class="stat-card__hint">
                <?= (int) ($s['books_never_read'] ?? 0) ?> livre<?= (int) ($s['books_never_read'] ?? 0) > 1 ? 's' : '' ?>
                jamais lu<?= (int) ($s['books_never_read'] ?? 0) > 1 ? 's' : '' ?>
            </p>
        </article>
    </div>

    <?php if ($totalBooks > 0 && $supportItems !== []): ?>
        <section class="stats-panel">
            <h2>Support</h2>
            <p class="hint">Répartition papier / numérique / autre dans votre collection.</p>
            <div class="support-chart note-chart" role="img" aria-label="Répartition par type de support">
                <?php foreach ($supportItems as $item):
                    $count = (int) ($item['count'] ?? 0);
                    $pctBar = $supportMax > 0 ? round(($count / $supportMax) * 100) : 0;
                    $label = (string) ($item['label'] ?? '');
                    $percent = (float) ($item['percent'] ?? 0);
                    ?>
                    <div class="note-chart__row support-chart__row">
                        <span class="note-chart__label support-chart__label"><?= Moncine\View::escape($label) ?></span>
                        <span class="note-chart__bar-wrap">
                            <span class="note-chart__bar support-chart__bar" style="width: <?= max(2, $pctBar) ?>%;"
                                  title="<?= $count ?> livre<?= $count > 1 ? 's' : '' ?>"></span>
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

    <?php if ($categoryItems !== []): ?>
        <section class="stats-panel">
            <h2>Catégories</h2>
            <p class="hint">Un même livre peut compter dans plusieurs catégories.</p>
            <div class="support-chart note-chart" role="img" aria-label="Répartition par catégorie">
                <?php foreach ($categoryItems as $item):
                    $count = (int) ($item['count'] ?? 0);
                    $pctBar = $categoryMax > 0 ? round(($count / $categoryMax) * 100) : 0;
                    $label = (string) ($item['label'] ?? '');
                    $percent = (float) ($item['percent'] ?? 0);
                    ?>
                    <div class="note-chart__row support-chart__row">
                        <span class="note-chart__label support-chart__label"><?= Moncine\View::escape($label) ?></span>
                        <span class="note-chart__bar-wrap">
                            <span class="note-chart__bar support-chart__bar" style="width: <?= max(2, $pctBar) ?>%;"
                                  title="<?= $count ?> livre<?= $count > 1 ? 's' : '' ?>"></span>
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

    <section class="stats-panel">
        <h2>Ressentis</h2>
        <?php if ((int) ($s['ressenti_count'] ?? 0) === 0): ?>
            <p class="hint">
                Aucun ressenti enregistré pour l’instant. Indiquez votre ressenti sur la fiche d’un livre
                (bouton note sous la couverture).
            </p>
        <?php else: ?>
            <div class="stats-notes-summary">
                <div class="stat-inline">
                    <span class="stat-inline__value"><?= (int) ($s['coups_de_coeur_count'] ?? 0) ?></span>
                    <span class="stat-inline__label">Coups de cœur (J’adore)</span>
                    <span class="stat-inline__hint"><?= (int) ($s['ressenti_count'] ?? 0) ?> ressenti<?= (int) ($s['ressenti_count'] ?? 0) > 1 ? 's' : '' ?> au total</span>
                </div>
                <?php if ((int) ($s['readings_sans_ressenti'] ?? 0) > 0): ?>
                    <p class="hint stat-inline__aside">
                        <?= (int) ($s['readings_sans_ressenti'] ?? 0) ?> lecture<?= (int) ($s['readings_sans_ressenti'] ?? 0) > 1 ? 's' : '' ?>
                        sans ressenti (date seule).
                    </p>
                <?php endif; ?>
            </div>

            <h3 class="stats-subtitle">Répartition des ressentis</h3>
            <div class="note-chart ressenti-chart" role="img"
                 aria-label="Répartition des ressentis (icônes)">
                <?php foreach ($ressentiKeys as $key):
                    $score = Moncine\RessentiNote::score($key);
                    $count = (int) ($noteDist[$score] ?? 0);
                    $pct = $noteMax > 0 ? round(($count / $noteMax) * 100) : 0;
                    ?>
                    <div class="note-chart__row ressenti-chart__row <?= Moncine\View::escape(Moncine\RessentiNote::cssClass($key)) ?>">
                        <span class="note-chart__label ressenti-chart__label"
                              title="<?= Moncine\View::escape(Moncine\RessentiNote::label($key)) ?>">
                            <span class="ressenti-chart__icon" aria-hidden="true"><?= Moncine\RessentiNote::iconSvg($key) ?></span>
                        </span>
                        <span class="note-chart__bar-wrap">
                            <span class="note-chart__bar" style="width: <?= max(2, $pct) ?>%;"
                                  title="<?= $count ?> ressenti<?= $count > 1 ? 's' : '' ?>"></span>
                        </span>
                        <span class="note-chart__count"><?= $count ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($readsByYear !== []): ?>
        <section class="stats-panel">
            <h2>Activité par année</h2>
            <p class="hint">
                Hauteur des barres = lectures enregistrées. Le chiffre sous l’année = livres différents lus
                (un même livre relu compte une seule fois).
            </p>
            <div class="year-chart">
                <?php foreach ($readsByYear as $row):
                    $readings = (int) ($row['readings'] ?? 0);
                    $books = (int) ($row['books'] ?? 0);
                    $pct = $yearChartMax > 0 ? round(($readings / $yearChartMax) * 100) : 0;
                    ?>
                    <div class="year-chart__item">
                        <span class="year-chart__bar-wrap">
                            <span class="year-chart__bar" style="height: <?= max(4, $pct) ?>%;"
                                  title="<?= $readings ?> lecture<?= $readings > 1 ? 's' : '' ?>, <?= $books ?> livre<?= $books > 1 ? 's' : '' ?>"></span>
                        </span>
                        <span class="year-chart__year"><?= (int) ($row['year'] ?? 0) ?></span>
                        <span class="year-chart__meta" title="<?= $readings ?> lecture<?= $readings > 1 ? 's' : '' ?>, <?= $books ?> livre<?= $books > 1 ? 's' : '' ?>">
                            <?= $books ?><?php if ($readings > $books): ?><span class="year-chart__meta-extra">/<?= $readings ?></span><?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php
    $topRated = $s['coups_de_coeur'] ?? [];
    $leastLiked = $s['moins_aimes'] ?? [];
    if ($topRated !== [] || $leastLiked !== []):
        ?>
        <section class="stats-panel">
            <div class="stats-films-columns">
                <?php if ($topRated !== []): ?>
                    <div class="stats-films-columns__col">
                        <h2>Coups de cœur</h2>
                        <ol class="stats-ranked-list">
                            <?php foreach ($topRated as $book): ?>
                                <li class="stats-ranked-list__item">
                                    <a href="<?= Moncine\View::escape(Moncine\View::livreUrl((int) $book['id'])) ?>" class="stats-ranked-list__link">
                                        <?= Moncine\View::escape((string) $book['titre']) ?>
                                    </a>
                                    <?php if (trim((string) ($book['auteur'] ?? '')) !== ''): ?>
                                        <span class="stats-ranked-list__meta">— <?= Moncine\View::escape((string) $book['auteur']) ?></span>
                                    <?php endif; ?>
                                    <span class="stats-ranked-list__ressenti">
                                        <?php
                                        $score = (int) ($book['best_note'] ?? Moncine\RessentiNote::MAX_SCORE);
                                        $showLabel = false;
                                        $size = 'small';
                                        require MONCINE_ROOT . '/templates/_ressenti_badge.php';
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>
                <?php if ($leastLiked !== []): ?>
                    <div class="stats-films-columns__col">
                        <h2>Livres les moins aimés</h2>
                        <ol class="stats-ranked-list">
                            <?php foreach ($leastLiked as $book): ?>
                                <li class="stats-ranked-list__item">
                                    <a href="<?= Moncine\View::escape(Moncine\View::livreUrl((int) $book['id'])) ?>" class="stats-ranked-list__link">
                                        <?= Moncine\View::escape((string) $book['titre']) ?>
                                    </a>
                                    <?php if (trim((string) ($book['auteur'] ?? '')) !== ''): ?>
                                        <span class="stats-ranked-list__meta">— <?= Moncine\View::escape((string) $book['auteur']) ?></span>
                                    <?php endif; ?>
                                    <span class="stats-ranked-list__ressenti">
                                        <?php
                                        $score = (int) ($book['best_note'] ?? 0);
                                        $showLabel = false;
                                        $size = 'small';
                                        require MONCINE_ROOT . '/templates/_ressenti_badge.php';
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php
    $reread = $s['most_reread'] ?? [];
    if ($reread !== []):
        ?>
        <section class="stats-panel">
            <h2>Livres relus le plus souvent</h2>
            <ol class="stats-ranked-list">
                <?php foreach ($reread as $book): ?>
                    <li>
                        <a href="<?= Moncine\View::escape(Moncine\View::livreUrl((int) $book['id'])) ?>" class="stats-ranked-list__link">
                            <?= Moncine\View::escape((string) $book['titre']) ?>
                        </a>
                        <span class="tag"><?= (int) ($book['read_count'] ?? 0) ?> fois</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php if ($totalBooks === 0): ?>
        <p class="alert alert-warning">
            Vous n’avez aucun livre enregistré.
            <a href="<?= Moncine\View::escape(Moncine\View::addLivreUrl(Moncine\LibraryStatut::COLLECTION)) ?>">Ajouter un livre</a>
        </p>
    <?php endif; ?>
</section>
