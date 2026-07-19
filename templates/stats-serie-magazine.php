<?php
/**
 * Statistiques d’évolution d’une série magazine.
 *
 * @var array<string, mixed>|null $series
 * @var string $statut
 * @var string|null $publicationTypeLabel
 * @var array<string, mixed>|null $stats
 */
$series = $series ?? null;
$statut = $statut ?? Moncine\LibraryStatut::COLLECTION;
$stats = $stats ?? null;
$publicationTypeLabel = $publicationTypeLabel ?? '';
?>
<section class="stats-page series-stats-page">
    <?php if ($series === null): ?>
        <h1>Série introuvable</h1>
        <p><a href="/magazines.php" class="btn btn-secondary">← Retour aux magazines</a></p>
    <?php else: ?>
        <?php
        $seriesId = (int) ($series['id'] ?? 0);
        $posterSrc = Moncine\View::seriesPosterSrc($series);
        $summary = is_array($stats['summary'] ?? null) ? $stats['summary'] : [];
        $pagesByYear = is_array($stats['pages_by_year'] ?? null) ? $stats['pages_by_year'] : [];
        $subjectsAvgByYear = is_array($stats['subjects_avg_by_year'] ?? null) ? $stats['subjects_avg_by_year'] : [];
        $subjectsByIssue = is_array($stats['subjects_by_issue'] ?? null) ? $stats['subjects_by_issue'] : [];
        $categoryKeys = is_array($stats['subject_category_keys'] ?? null)
            ? $stats['subject_category_keys']
            : array_keys(Moncine\MagazineSubject::choices());
        $categoryChoices = Moncine\MagazineSubject::choices();

        $issueCount = (int) ($summary['issue_count'] ?? 0);
        $issuesWithPages = (int) ($summary['issues_with_pages'] ?? 0);
        $issuesWithoutPages = (int) ($summary['issues_without_pages'] ?? 0);
        $issuesWithSubjects = (int) ($summary['issues_with_subjects'] ?? 0);
        $issuesWithoutSubjects = (int) ($summary['issues_without_subjects'] ?? 0);
        $subjectLinkCount = (int) ($summary['subject_link_count'] ?? 0);

        $pagesAvg = $summary['pages_avg'] ?? null;
        $pagesMin = $summary['pages_min'] ?? null;
        $pagesMax = $summary['pages_max'] ?? null;

        // Hauteur max des barres « pages par année »
        $pagesYearMax = 0.0;
        foreach ($pagesByYear as $row) {
            $pagesYearMax = max($pagesYearMax, (float) ($row['avg_pages'] ?? 0));
        }

        // Hauteur max des moyennes de sujets par année
        $subjectsAvgMax = 0.0;
        foreach ($subjectsAvgByYear as $row) {
            $categories = is_array($row['categories'] ?? null) ? $row['categories'] : [];
            foreach ($categories as $avg) {
                $subjectsAvgMax = max($subjectsAvgMax, (float) $avg);
            }
        }

        // Hauteur max des sujets par numéro
        $subjectsIssueMax = 0;
        foreach ($subjectsByIssue as $row) {
            $categories = is_array($row['categories'] ?? null) ? $row['categories'] : [];
            foreach ($categories as $count) {
                $subjectsIssueMax = max($subjectsIssueMax, (int) $count);
            }
        }

        // Catégories présentes (légende partagée)
        $activeCategoryKeys = [];
        foreach ($categoryKeys as $key) {
            $key = (string) $key;
            $found = false;
            foreach ($subjectsAvgByYear as $row) {
                $categories = is_array($row['categories'] ?? null) ? $row['categories'] : [];
                if ((float) ($categories[$key] ?? 0) > 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                foreach ($subjectsByIssue as $row) {
                    $categories = is_array($row['categories'] ?? null) ? $row['categories'] : [];
                    if ((int) ($categories[$key] ?? 0) > 0) {
                        $found = true;
                        break;
                    }
                }
            }
            if ($found) {
                $activeCategoryKeys[] = $key;
            }
        }

        $formatAvg = static function (float $value): string {
            if (abs($value - round($value)) < 0.001) {
                return (string) (int) round($value);
            }

            return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
        };
        ?>
        <header class="series-stats-page__header">
            <p>
                <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl($seriesId, 'numero_ordre', 'desc', [
                    'statut' => $statut,
                ])) ?>"
                   class="btn btn-secondary btn-sm">← Retour à la série</a>
            </p>
            <div class="magazine-series-header__main">
                <?php if ($posterSrc !== ''): ?>
                    <img src="<?= $posterSrc ?>" alt="" class="magazine-cover magazine-cover--header">
                <?php endif; ?>
                <div>
                    <h1>Statistiques — <?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h1>
                    <p class="lead">
                        <?= Moncine\View::escape($publicationTypeLabel) ?>
                        <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                            · <?= Moncine\View::escape((string) $series['editeur']) ?>
                        <?php endif; ?>
                        · <?= $issueCount ?> numéro<?= $issueCount > 1 ? 's' : '' ?> au catalogue
                    </p>
                    <p class="hint">
                        Ces graphiques se remplissent au fur et à mesure : indiquez le nombre de pages
                        et les sujets (tests, previews…) sur chaque numéro.
                    </p>
                </div>
            </div>
        </header>

        <div class="stats-grid">
            <article class="stat-card stat-card--highlight">
                <p class="stat-card__value"><?= $issueCount ?></p>
                <p class="stat-card__label">Numéros au catalogue</p>
            </article>
            <article class="stat-card">
                <p class="stat-card__value">
                    <?= $pagesAvg !== null ? Moncine\View::escape((string) $pagesAvg) : '—' ?>
                </p>
                <p class="stat-card__label">Pages en moyenne</p>
                <p class="stat-card__hint">
                    <?= $issuesWithPages ?> numéro<?= $issuesWithPages > 1 ? 's' : '' ?> renseigné<?= $issuesWithPages > 1 ? 's' : '' ?>
                    <?php if ($issuesWithoutPages > 0): ?>
                        · <?= $issuesWithoutPages ?> sans pages
                    <?php endif; ?>
                </p>
            </article>
            <article class="stat-card">
                <p class="stat-card__value">
                    <?php if ($pagesMin !== null && $pagesMax !== null): ?>
                        <?= (int) $pagesMin ?>–<?= (int) $pagesMax ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </p>
                <p class="stat-card__label">Pages (min – max)</p>
            </article>
            <article class="stat-card">
                <p class="stat-card__value"><?= $subjectLinkCount ?></p>
                <p class="stat-card__label">Sujets recensés</p>
                <p class="stat-card__hint">
                    <?= $issuesWithSubjects ?> numéro<?= $issuesWithSubjects > 1 ? 's' : '' ?> avec sujet<?= $issuesWithSubjects > 1 ? 's' : '' ?>
                    <?php if ($issuesWithoutSubjects > 0): ?>
                        · <?= $issuesWithoutSubjects ?> sans sujet
                    <?php endif; ?>
                </p>
            </article>
        </div>

        <section class="stats-panel">
            <h2>Évolution du nombre de pages</h2>
            <?php if ($pagesByYear === []): ?>
                <p class="hint">
                    Aucune donnée pour l’instant. Renseignez le champ <strong>Pages</strong>
                    (et la date de parution) sur les fiches numéros pour voir la courbe apparaître.
                </p>
            <?php else: ?>
                <p class="hint">
                    Hauteur des barres = <strong>moyenne de pages</strong> des numéros de l’année
                    (uniquement ceux dont le nombre de pages est renseigné).
                </p>
                <div class="year-chart series-stats-pages-chart" role="img"
                     aria-label="Moyenne de pages par année">
                    <?php foreach ($pagesByYear as $row):
                        $year = (int) ($row['year'] ?? 0);
                        $avg = (float) ($row['avg_pages'] ?? 0);
                        $count = (int) ($row['issue_count'] ?? 0);
                        $pct = $pagesYearMax > 0 ? round(($avg / $pagesYearMax) * 100) : 0;
                        $title = sprintf(
                            '%s : %.0f pages en moyenne (%d numéro%s)',
                            (string) $year,
                            $avg,
                            $count,
                            $count > 1 ? 's' : ''
                        );
                        ?>
                        <div class="year-chart__item">
                            <span class="year-chart__bar-wrap">
                                <span class="year-chart__bar" style="height: <?= max(4, $pct) ?>%;"
                                      title="<?= Moncine\View::escape($title) ?>"></span>
                            </span>
                            <span class="year-chart__year"><?= $year ?></span>
                            <span class="year-chart__meta" title="<?= Moncine\View::escape($title) ?>">
                                <?= $avg == floor($avg) ? (string) (int) $avg : Moncine\View::escape((string) $avg) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="stats-panel">
            <h2>Répartition des sujets</h2>
            <?php if ($subjectsAvgByYear === [] || $activeCategoryKeys === []): ?>
                <p class="hint">
                    Aucun sujet pour l’instant. Ajoutez des tests, previews, soluces…
                    sur les fiches numéros pour voir la répartition.
                </p>
            <?php else: ?>
                <p class="hint">
                    Pour chaque année : <strong>moyenne de sujets par numéro</strong>
                    (total de la catégorie ÷ nombre de numéros de l’année qui ont déjà des sujets).
                    Les numéros sans aucun sujet sont ignorés.
                </p>
                <ul class="series-stats-legend" aria-label="Légende des catégories">
                    <?php foreach ($activeCategoryKeys as $key):
                        $key = (string) $key;
                        $label = (string) ($categoryChoices[$key] ?? $key);
                        ?>
                        <li class="series-stats-legend__item">
                            <span class="series-stats-legend__swatch series-stats-category-bar--<?= Moncine\View::escape($key) ?>"
                                  aria-hidden="true"></span>
                            <?= Moncine\View::escape($label) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="series-stats-grouped-chart" role="img"
                     aria-label="Moyenne de sujets par numéro et par année">
                    <?php foreach ($subjectsAvgByYear as $row):
                        $year = (int) ($row['year'] ?? 0);
                        $issueCountYear = (int) ($row['issue_count'] ?? 0);
                        $categories = is_array($row['categories'] ?? null) ? $row['categories'] : [];
                        $counts = is_array($row['counts'] ?? null) ? $row['counts'] : [];
                        $totalAvg = (float) ($row['total_avg'] ?? 0);
                        $tooltipParts = [];
                        foreach ($activeCategoryKeys as $key) {
                            $key = (string) $key;
                            $avg = (float) ($categories[$key] ?? 0);
                            if ($avg > 0) {
                                $tooltipParts[] = ($categoryChoices[$key] ?? $key) . ' : '
                                    . $formatAvg($avg) . '/n° ('
                                    . (int) ($counts[$key] ?? 0) . ' au total)';
                            }
                        }
                        $yearTitle = (string) $year . ' — ' . $issueCountYear . ' numéro'
                            . ($issueCountYear > 1 ? 's' : '') . ' — ' . implode(', ', $tooltipParts);
                        ?>
                        <div class="series-stats-grouped-chart__item">
                            <div class="series-stats-grouped-chart__bars">
                                <?php foreach ($activeCategoryKeys as $key):
                                    $key = (string) $key;
                                    $avg = (float) ($categories[$key] ?? 0);
                                    $pct = $subjectsAvgMax > 0 ? round(($avg / $subjectsAvgMax) * 100) : 0;
                                    $catLabel = (string) ($categoryChoices[$key] ?? $key);
                                    ?>
                                    <span class="series-stats-grouped-chart__bar series-stats-category-bar--<?= Moncine\View::escape($key) ?>"
                                          style="height: <?= $avg > 0 ? max(4, $pct) : 0 ?>%;"
                                          title="<?= Moncine\View::escape($catLabel . ' : ' . $formatAvg($avg) . ' / numéro') ?>"></span>
                                <?php endforeach; ?>
                            </div>
                            <span class="series-stats-grouped-chart__year"
                                  title="<?= Moncine\View::escape($yearTitle) ?>"><?= $year ?></span>
                            <span class="series-stats-grouped-chart__meta"
                                  title="<?= Moncine\View::escape($yearTitle) ?>"><?= Moncine\View::escape($formatAvg($totalAvg)) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="stats-panel">
            <h2>Évolution des sujets par numéro</h2>
            <?php if ($subjectsByIssue === [] || $activeCategoryKeys === []): ?>
                <p class="hint">
                    Pas encore de sujets pour tracer l’évolution numéro par numéro.
                </p>
            <?php else: ?>
                <p class="hint">
                    Pour chaque numéro : une barre par type d’article
                    (hauteur = nombre de sujets de ce type dans ce numéro).
                </p>
                <ul class="series-stats-legend" aria-label="Légende des catégories">
                    <?php foreach ($activeCategoryKeys as $key):
                        $key = (string) $key;
                        $label = (string) ($categoryChoices[$key] ?? $key);
                        ?>
                        <li class="series-stats-legend__item">
                            <span class="series-stats-legend__swatch series-stats-category-bar--<?= Moncine\View::escape($key) ?>"
                                  aria-hidden="true"></span>
                            <?= Moncine\View::escape($label) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="series-stats-grouped-chart series-stats-grouped-chart--by-issue" role="img"
                     aria-label="Nombre de sujets par catégorie et par numéro">
                    <?php foreach ($subjectsByIssue as $row):
                        $numeroLabel = (string) ($row['numero_label'] ?? '—');
                        $categories = is_array($row['categories'] ?? null) ? $row['categories'] : [];
                        $total = (int) ($row['total'] ?? 0);
                        $year = $row['year'] ?? null;
                        $tooltipParts = [];
                        foreach ($activeCategoryKeys as $key) {
                            $key = (string) $key;
                            $c = (int) ($categories[$key] ?? 0);
                            if ($c > 0) {
                                $tooltipParts[] = ($categoryChoices[$key] ?? $key) . ' : ' . $c;
                            }
                        }
                        $issueTitle = $numeroLabel
                            . (is_int($year) ? ' (' . $year . ')' : '')
                            . ' — ' . implode(', ', $tooltipParts);
                        ?>
                        <div class="series-stats-grouped-chart__item">
                            <div class="series-stats-grouped-chart__bars">
                                <?php foreach ($activeCategoryKeys as $key):
                                    $key = (string) $key;
                                    $c = (int) ($categories[$key] ?? 0);
                                    $pct = $subjectsIssueMax > 0 ? round(($c / $subjectsIssueMax) * 100) : 0;
                                    $catLabel = (string) ($categoryChoices[$key] ?? $key);
                                    ?>
                                    <span class="series-stats-grouped-chart__bar series-stats-category-bar--<?= Moncine\View::escape($key) ?>"
                                          style="height: <?= $c > 0 ? max(4, $pct) : 0 ?>%;"
                                          title="<?= Moncine\View::escape($catLabel . ' : ' . $c) ?>"></span>
                                <?php endforeach; ?>
                            </div>
                            <span class="series-stats-grouped-chart__year"
                                  title="<?= Moncine\View::escape($issueTitle) ?>"><?= Moncine\View::escape($numeroLabel) ?></span>
                            <span class="series-stats-grouped-chart__meta"
                                  title="<?= Moncine\View::escape($issueTitle) ?>"><?= $total ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
