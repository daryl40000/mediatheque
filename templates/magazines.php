<?php
/** @var list<array<string, mixed>> $seriesList */
/** @var string $query */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var int $totalCount */
/** @var string $moduleError */
/** @var list<array<string, mixed>> $contentSubjects */
/** @var list<array<string, mixed>> $contentIssues */
$contentSubjects = $contentSubjects ?? [];
$contentIssues = $contentIssues ?? [];
$hasContentSearch = trim($query) !== '';
/** @var bool $canManageCatalog */
$canManageCatalog = $canManageCatalog ?? false;
?>
<section class="collection-page">
    <header class="collection-page__header">
    <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></h1>
        <p class="lead">
            Vos revues et magazines sont regroupés par <strong>série</strong>
            (PC Jeux, Joystick, Warhammer…). Cliquez sur une série pour voir les numéros.
        </p>
        <div class="collection-page__actions">
            <a href="/ajouter-serie-magazine.php" class="btn btn-accent">Ajouter une série</a>
            <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectSearchUrl()) ?>" class="btn btn-secondary">Recherche par sujet</a>
            <?php if ($canManageCatalog): ?>
                <a href="/import-catalogue-magazines.php" class="btn btn-secondary">Import catalogue (JSON)</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['series_removed'])): ?>
        <div class="alert alert-success">
            La revue a été retirée de vos magazines.
            <?php if (isset($_GET['removed_issues']) && (int) $_GET['removed_issues'] > 0): ?>
                <?= (int) $_GET['removed_issues'] ?> numéro(s) ont aussi été retirés de votre bibliothèque.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="get" action="/magazines.php" class="collection-search magazine-global-search">
        <div class="magazine-subject-search__row magazine-subject-search__row--autocomplete magazine-global-search__field"
             data-magazine-subject-autocomplete="navigate"
             data-search-url="<?= Moncine\View::escape(Moncine\View::magazineSubjectApiUrl()) ?>">
            <label for="mag_q">Rechercher dans vos magazines</label>
            <input type="search" name="q" id="mag_q"
                   value="<?= Moncine\View::escape($query) ?>"
                   placeholder="Série, test, dossier, sommaire, texte PDF…"
                   autocomplete="off" autocapitalize="off" spellcheck="false"
                   aria-autocomplete="list" aria-controls="magazine-global-suggestions">
            <ul class="catalog-title-autocomplete__list magazine-subject-suggestions" id="magazine-global-suggestions"
                role="listbox" hidden></ul>
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
        <p class="hint magazine-global-search__hint">
            Cherche dans les titres de séries, les sujets (tests, previews, dossiers), les sommaires et les extraits PDF.
        </p>
    </form>

    <?php if ($hasContentSearch): ?>
        <?php if ($contentSubjects !== []): ?>
            <section class="magazine-global-results" aria-labelledby="magazine-global-subjects-heading">
                <h2 id="magazine-global-subjects-heading">Sujets trouvés</h2>
                <ul class="magazine-subject-results" role="list">
                    <?php foreach ($contentSubjects as $subject): ?>
                        <?php
                        $subjectId = (int) ($subject['id'] ?? 0);
                        $issueCount = (int) ($subject['library_issue_count'] ?? 0);
                        ?>
                        <li class="magazine-subject-results__item" role="listitem">
                            <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectUrl($subjectId)) ?>"
                               class="magazine-subject-results__link">
                                <span class="magazine-tag magazine-tag--subject">
                                    <?= Moncine\View::escape((string) ($subject['category_label'] ?? '')) ?>
                                </span>
                                <strong><?= Moncine\View::escape((string) ($subject['display_label'] ?? '')) ?></strong>
                                <span class="hint">
                                    <?= $issueCount ?> numéro<?= $issueCount > 1 ? 's' : '' ?> dans votre bibliothèque
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($contentIssues !== []): ?>
            <section class="magazine-global-results" aria-labelledby="magazine-global-issues-heading">
                <h2 id="magazine-global-issues-heading">Numéros trouvés</h2>
                <div class="magazine-issues-grid magazine-global-results__issues">
                    <?php foreach ($contentIssues as $issue): ?>
                        <?php
                        $bibId = (int) ($issue['bib_id'] ?? 0);
                        $issueUrl = Moncine\View::magazineIssueUrl($bibId);
                        $poster = trim((string) ($issue['poster_url'] ?? ''));
                        $posterSrc = Moncine\View::posterSrc($poster !== '' ? $poster : null);
                        ?>
                        <article class="magazine-issue-card">
                            <a href="<?= Moncine\View::escape($issueUrl) ?>" class="magazine-issue-card__cover-link">
                                <?php if ($posterSrc !== ''): ?>
                                    <img src="<?= $posterSrc ?>" alt="" class="magazine-issue-card__cover" loading="lazy">
                                <?php else: ?>
                                    <div class="magazine-issue-card__cover magazine-issue-card__cover--empty" aria-hidden="true"></div>
                                <?php endif; ?>
                            </a>
                            <div class="magazine-issue-card__body">
                                <p class="hint"><?= Moncine\View::escape((string) ($issue['series_titre'] ?? '')) ?></p>
                                <h3 class="magazine-issue-card__title">
                                    <a href="<?= Moncine\View::escape($issueUrl) ?>">
                                        <?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?>
                                    </a>
                                </h3>
                                <p class="magazine-issue-card__meta hint">
                                    <?= Moncine\View::escape(Moncine\PublicationType::formatParutionDate(
                                        (string) ($issue['date_parution'] ?? ''),
                                        (string) ($issue['publication_type'] ?? '')
                                    )) ?>
                                </p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($contentSubjects === [] && $contentIssues === [] && $totalCount === 0): ?>
            <p class="hint">Aucun résultat. Essayez un autre mot-clé ou vérifiez l’orthographe.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($totalCount === 0 && !$hasContentSearch): ?>
        <p class="hint">Aucune série en collection. Commencez par créer une série, puis ajoutez des numéros.</p>
    <?php elseif ($totalCount > 0): ?>
        <h2 class="magazine-global-results__series-heading">
            <?= $hasContentSearch ? 'Séries correspondantes' : 'Vos séries' ?>
        </h2>
        <p class="stats"><?= (int) $totalCount ?> série(s)<?= $hasContentSearch ? ' correspondante(s)' : ' en collection' ?>.</p>
        <div class="magazine-series-grid">
            <?php foreach ($seriesList as $series): ?>
                <?php
                $seriesId = (int) ($series['id'] ?? 0);
                $poster = trim((string) ($series['poster_url'] ?? $series['latest_poster_url'] ?? ''));
                $posterSrc = Moncine\View::posterSrc($poster !== '' ? $poster : null);
                ?>
                <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl($seriesId)) ?>"
                   class="magazine-series-card">
                    <?php if ($posterSrc !== ''): ?>
                        <img src="<?= $posterSrc ?>" alt="" class="magazine-series-card__cover" loading="lazy">
                    <?php else: ?>
                        <div class="magazine-series-card__cover magazine-series-card__cover--empty" aria-hidden="true"></div>
                    <?php endif; ?>
                    <div class="magazine-series-card__body">
                        <h2 class="magazine-series-card__title"><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h2>
                        <p class="hint">
                            <?= Moncine\View::escape(Moncine\PublicationType::label((string) ($series['publication_type'] ?? ''))) ?>
                            · <?= (int) ($series['issue_count'] ?? 0) ?> numéro(s) possédé(s)<?= (int) ($series['issue_count'] ?? 0) === 0 ? ' — ajoutez le premier' : '' ?>
                        </p>
                        <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                            <p class="hint"><?= Moncine\View::escape((string) $series['editeur']) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php elseif ($hasContentSearch && $totalCount === 0 && ($contentSubjects !== [] || $contentIssues !== [])): ?>
        <p class="hint">Aucune série ne correspond directement au titre, mais des sujets ou numéros ont été trouvés ci-dessus.</p>
    <?php endif; ?>
</section>
