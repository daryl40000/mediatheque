<?php
/**
 * Fiche d’un supplément magazine (comme un numéro : couverture, PDF, sujets).
 *
 * @var array<string, mixed>|null $issue
 * @var array<string, mixed>|null $supplement
 * @var string $pdfUrl
 * @var string $dateLabel
 * @var string $error
 */
$issue = $issue ?? null;
$supplement = $supplement ?? null;
$pdfUrl = trim((string) ($pdfUrl ?? ''));
$error = (string) ($error ?? '');
$dateLabel = (string) ($dateLabel ?? '');
$subjectTargetSupplementId = (int) ($subjectTargetSupplementId ?? 0);
?>
<section class="collection-page game-detail-page">
    <?php if ($issue === null || $supplement === null): ?>
        <h1>Supplément introuvable</h1>
        <p class="hint">Ce livret / PDF bonus n’existe pas ou n’est plus accessible.</p>
        <p>
            <?php if ($issue !== null): ?>
                <a href="<?= Moncine\View::escape(Moncine\View::magazineIssueUrl((int) ($issue['bib_id'] ?? 0))) ?>"
                   class="btn btn-secondary">← Retour au numéro</a>
            <?php else: ?>
                <a href="/magazines.php" class="btn btn-secondary">← Magazines</a>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <?php
        $bibId = (int) ($issue['bib_id'] ?? 0);
        $seriesId = (int) ($issue['series_id'] ?? 0);
        $pageStatut = (string) ($issue['statut'] ?? Moncine\LibraryStatut::COLLECTION);
        $issuePageUrl = Moncine\View::magazineIssueUrl($bibId);
        $seriesBackUrl = Moncine\View::magazineSeriesUrl($seriesId, 'numero_ordre', 'desc', ['statut' => $pageStatut]);
        $suppLabel = (string) ($supplement['display_label'] ?? 'Supplément');
        $cover = Moncine\View::posterSrc($supplement['cover_url'] ?? null);
        $pages = (int) ($supplement['pages'] ?? 0);
        ?>

        <div class="magazine-issue-toolbar">
            <p class="magazine-issue-toolbar__back">
                <a href="<?= Moncine\View::escape($issuePageUrl) ?>" class="btn btn-secondary btn-sm">
                    ← N° <?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?>
                    — <?= Moncine\View::escape((string) ($issue['series_titre'] ?? 'Numéro')) ?>
                </a>
            </p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
        <?php endif; ?>

        <article class="film-detail game-detail film-detail--with-poster">
            <aside class="game-detail-sidebar" aria-label="Couverture du supplément">
                <?php if ($cover !== ''): ?>
                    <img class="film-poster film-poster--large film-poster--bd game-detail-sidebar__poster"
                         src="<?= $cover ?>"
                         alt="Couverture de <?= Moncine\View::escape($suppLabel) ?>">
                <?php else: ?>
                    <span class="film-poster film-poster--large film-poster--bd film-poster--empty game-detail-sidebar__poster"
                          aria-hidden="true"></span>
                <?php endif; ?>

                <div class="game-detail-sidebar__actions">
                    <?php if ($pdfUrl !== ''): ?>
                        <a href="<?= Moncine\View::escape($pdfUrl) ?>"
                           class="btn btn-accent btn-sm magazine-issue-card__pdf"
                           target="_blank"
                           rel="noopener"
                           title="Ouvrir le PDF"
                           aria-label="Ouvrir le PDF du supplément">PDF</a>
                    <?php endif; ?>
                    <a href="<?= Moncine\View::escape($issuePageUrl . '&popover=pdf') ?>"
                       class="btn btn-icon btn-secondary btn-sm"
                       title="Gérer les suppléments du numéro"
                       aria-label="Retour à la gestion PDF du numéro">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2 5 5h-5V4zM8 13h8v2H8v-2zm0 4h8v2H8v-2z"/>
                        </svg>
                    </a>
                </div>
            </aside>

            <div class="film-detail__body game-detail__body">
                <header class="film-detail__heading game-detail__heading">
                    <div class="game-detail__title-bar">
                        <h1 class="game-detail__title-row">
                            <span><?= Moncine\View::escape($suppLabel) ?></span>
                        </h1>
                    </div>
                    <p class="game-detail__saga">
                        Supplément du numéro
                        <strong><?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?></strong>
                        · <?= Moncine\View::escape((string) ($issue['series_titre'] ?? '')) ?>
                        <?php if ($dateLabel !== ''): ?>
                            · <?= Moncine\View::escape($dateLabel) ?>
                        <?php endif; ?>
                        <?php if ($pages > 0): ?>
                            · <?= $pages ?> p.
                        <?php endif; ?>
                        <span class="magazine-tag magazine-tag--subject">Supplément</span>
                    </p>
                    <p class="hint">
                        Associez ici les sujets présents dans ce livret (jeux, films…).
                        Ils apparaîtront comme sur une fiche numéro.
                    </p>
                </header>

                <div class="magazine-sommaire-row<?= ($offeredSubjects ?? []) !== [] ? ' magazine-sommaire-row--with-offers' : '' ?>">
                    <section class="game-detail__facts magazine-sommaire-row__sommaire"
                             aria-labelledby="magazine-supplement-info-heading">
                        <h2 id="magazine-supplement-info-heading" class="game-detail__section-title">À propos</h2>
                        <p class="hint">
                            Fichier :
                            <strong><?= Moncine\View::escape((string) ($supplement['original_filename'] ?? $suppLabel)) ?></strong>
                            <?php if (!empty($supplement['size_label'])): ?>
                                · <?= Moncine\View::escape((string) $supplement['size_label']) ?>
                            <?php endif; ?>
                        </p>
                    </section>
                    <?php
                    $offeredSubjects = $offeredSubjects ?? [];
                    require MONCINE_ROOT . '/templates/_magazine_issue_jeux_offerts.php';
                    ?>
                </div>

                <?php require MONCINE_ROOT . '/templates/_magazine_issue_subjects.php'; ?>

                <div class="result-actions">
                    <a href="<?= Moncine\View::escape($issuePageUrl) ?>" class="btn btn-ghost">← Retour au numéro</a>
                    <a href="<?= Moncine\View::escape($seriesBackUrl) ?>" class="btn btn-ghost">Série</a>
                </div>
            </div>
        </article>
    <?php endif; ?>
</section>
