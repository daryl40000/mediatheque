<?php
/** @var array<string, mixed>|null $subject */
/** @var array{issue_count: int, series_count: int} $stats */
/** @var list<array<string, mixed>> $issues */
/** @var string $moduleError */
/** @var int $page */
/** @var int $totalPages */
/** @var int $listTotal */
?>
<section class="collection-page">
    <?php if ($subject === null): ?>
        <h1>Sujet introuvable</h1>
        <p class="alert alert-warning">Ce sujet n’existe pas ou a été supprimé.</p>
        <p><a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectSearchUrl()) ?>" class="btn btn-secondary">← Recherche par sujet</a></p>
    <?php else: ?>
        <?php $subjectId = (int) ($subject['id'] ?? 0); ?>
        <header class="collection-page__header">
            <p>
                <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectSearchUrl()) ?>" class="btn btn-secondary btn-sm">← Recherche</a>
            </p>
            <p>
                <span class="magazine-tag magazine-tag--subject">
                    <?= Moncine\View::escape((string) ($subject['category_label'] ?? '')) ?>
                </span>
            </p>
            <h1><?= Moncine\View::escape((string) ($subject['display_label'] ?? '')) ?></h1>
            <?php if (!empty($subject['catalog_game_url'])): ?>
                <p>
                    <a href="<?= Moncine\View::escape((string) $subject['catalog_game_url']) ?>" class="btn btn-secondary btn-sm">
                        Voir la fiche jeu liée
                    </a>
                </p>
            <?php elseif (!empty($subject['catalog_game'])): ?>
                <p class="hint">
                    Relié au catalogue jeux :
                    <strong><?= Moncine\View::escape((string) ($subject['catalog_game']['display_label'] ?? '')) ?></strong>
                    (pas encore dans votre bibliothèque jeux).
                </p>
            <?php endif; ?>
            <p class="stats">
                <?= (int) ($stats['issue_count'] ?? 0) ?> numéro<?= (int) ($stats['issue_count'] ?? 0) > 1 ? 's' : '' ?>
                dans <?= (int) ($stats['series_count'] ?? 0) ?> série<?= (int) ($stats['series_count'] ?? 0) > 1 ? 's' : '' ?>
            </p>
        </header>

        <?php if ($moduleError !== ''): ?>
            <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
        <?php elseif ($issues === []): ?>
            <p class="hint">Aucun numéro de votre bibliothèque n’est associé à ce sujet pour le moment.</p>
        <?php else: ?>
            <div class="magazine-issues-grid">
                <?php foreach ($issues as $row): ?>
                    <?php
                    $bibId = (int) ($row['bib_id'] ?? 0);
                    $issueUrl = Moncine\View::magazineIssueUrl($bibId);
                    $seriesUrl = Moncine\View::magazineSeriesUrl((int) ($row['series_id'] ?? 0));
                    $cover = Moncine\View::posterSrc(trim((string) ($row['poster_url'] ?? '')) ?: null);
                    $dateLabel = Moncine\PublicationType::formatParutionDate(
                        (string) ($row['date_parution'] ?? ''),
                        (string) ($row['publication_type'] ?? '')
                    );
                    ?>
                    <article class="magazine-issue-card">
                        <a href="<?= Moncine\View::escape($issueUrl) ?>" class="magazine-issue-card__cover-link">
                            <?php if ($cover !== ''): ?>
                                <img src="<?= $cover ?>" alt="" class="magazine-cover magazine-cover--card" loading="lazy">
                            <?php else: ?>
                                <span class="magazine-cover magazine-cover--card magazine-cover--empty" aria-hidden="true"></span>
                            <?php endif; ?>
                        </a>
                        <div class="magazine-issue-card__body">
                            <p class="hint">
                                <a href="<?= Moncine\View::escape($seriesUrl) ?>">
                                    <?= Moncine\View::escape((string) ($row['series_titre'] ?? '')) ?>
                                </a>
                            </p>
                            <h2 class="magazine-issue-card__title">
                                N° <?= Moncine\View::escape((string) ($row['numero'] ?? '')) ?>
                            </h2>
                            <p class="magazine-issue-card__meta hint">
                                <?= Moncine\View::escape($dateLabel) ?>
                                <?php $issue = $row; require MONCINE_ROOT . '/templates/_magazine_support_tags.php'; ?>
                            </p>
                            <div class="magazine-issue-card__actions">
                                <a href="<?= Moncine\View::escape($issueUrl) ?>" class="btn btn-secondary btn-sm">Fiche numéro</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="list-pager" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectUrl($subjectId) . '&page=' . ($page - 1)) ?>">← Préc.</a>
                    <?php endif; ?>
                    <span>Page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectUrl($subjectId) . '&page=' . ($page + 1)) ?>">Suiv. →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
