<?php
/** @var list<array<string, mixed>> $seriesList */
/** @var string $query */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var int $totalCount */
/** @var string $moduleError */
/** @var list<array<string, mixed>> $contentSubjects */
/** @var list<array<string, mixed>> $contentIssues */
/** @var list<array{key: string, label: string, count: int}> $categoryFilterChoices */
$contentSubjects = $contentSubjects ?? [];
$contentIssues = $contentIssues ?? [];
$categoryFilterChoices = $categoryFilterChoices ?? [];
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
                <div class="magazine-issues-grid magazine-issues-grid--compact magazine-global-results__issues">
                    <?php foreach ($contentIssues as $issue): ?>
                        <?php
                        $row = $issue;
                        $series = null;
                        $showSeriesTitleInBubble = true;
                        $showFooter = false;
                        $isWishlist = false;
                        require MONCINE_ROOT . '/templates/_magazine_issue_grid_card.php';
                        ?>
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
        <p class="stats" data-magazine-series-stats
           data-stats-label="<?= $hasContentSearch ? ' correspondante(s)' : ' en collection' ?>."
           data-stats-label-single="<?= $hasContentSearch ? ' correspondante' : ' en collection' ?>."
           data-stats-visible=" série(s) affichée(s)."
           data-stats-visible-single=" série affichée.">
            <?= (int) $totalCount ?> série(s)<?= $hasContentSearch ? ' correspondante(s)' : ' en collection' ?>.
        </p>
        <p class="hint magazine-category-rail__empty" data-magazine-category-empty hidden>
            Aucune revue ne correspond aux catégories sélectionnées.
        </p>
        <div class="magazine-series-grid magazine-series-grid--poster-only" data-magazine-series-grid>
            <?php foreach ($seriesList as $series): ?>
                <?php
                $seriesId = (int) ($series['id'] ?? 0);
                $posterSrc = Moncine\View::seriesPosterSrc($series);
                $seriesCategoryKeys = Moncine\MagazineSeriesCategory::filterKeysForSeries($series);
                $seriesTitle = (string) ($series['titre'] ?? '');
                $ariaLabel = $seriesTitle;
                $issueCount = (int) ($series['issue_count'] ?? 0);
                if ($issueCount > 0) {
                    $ariaLabel .= ', ' . $issueCount . ' numéro' . ($issueCount > 1 ? 's' : '') . ' possédé' . ($issueCount > 1 ? 's' : '');
                }
                ?>
                <article class="magazine-series-card"
                         data-series-categories="<?= Moncine\View::escape(implode(',', $seriesCategoryKeys)) ?>">
                    <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl($seriesId)) ?>"
                       class="magazine-series-card__link"
                       aria-label="<?= Moncine\View::escape($ariaLabel) ?>">
                        <?php if ($posterSrc !== ''): ?>
                            <img src="<?= $posterSrc ?>" alt="" class="magazine-series-card__cover" loading="lazy">
                        <?php else: ?>
                            <div class="magazine-series-card__cover magazine-series-card__cover--empty" aria-hidden="true"></div>
                        <?php endif; ?>
                    </a>
                    <div class="collection-grid__hover-bubble" aria-hidden="true">
                        <?php require MONCINE_ROOT . '/templates/_magazine_series_grid_caption.php'; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php elseif ($hasContentSearch && $totalCount === 0 && ($contentSubjects !== [] || $contentIssues !== [])): ?>
        <p class="hint">Aucune série ne correspond directement au titre, mais des sujets ou numéros ont été trouvés ci-dessus.</p>
    <?php endif; ?>
</section>
