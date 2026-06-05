<?php
/**
 * Grille lecture seule des numéros magazine sur un profil public.
 *
 * @var list<array<string, mixed>> $issues
 * @var array<string, mixed> $series
 * @var int $targetUserId
 * @var string $statut
 * @var string $listMode
 */
$isWishlist = ($statut ?? '') === Moncine\LibraryStatut::WISHLIST;
?>
<div class="magazine-issues-grid" id="magazine-issues-grid">
    <?php foreach ($issues as $row): ?>
        <?php
        $bibId = (int) ($row['bib_id'] ?? 0);
        $issueUrl = Moncine\View::userProfileMagazineIssueUrl($targetUserId, $bibId);
        $cover = Moncine\View::posterSrc(trim((string) ($row['poster_url'] ?? '')) ?: null);
        $dateLabel = Moncine\PublicationType::formatParutionDate(
            (string) ($row['date_parution'] ?? ''),
            (string) ($row['publication_type'] ?? $series['publication_type'] ?? '')
        );
        $pages = (int) ($row['pages'] ?? 0);
        $isPossessed = Moncine\MagazineSupport::isPossessed($row);
        $cardClass = 'magazine-issue-card';
        if (!$isWishlist && !$isPossessed) {
            $cardClass .= ' magazine-issue-card--unowned';
        }
        ?>
        <article class="<?= Moncine\View::escape($cardClass) ?>">
            <a href="<?= Moncine\View::escape($issueUrl) ?>" class="magazine-issue-card__cover-link">
                <?php if ($cover !== ''): ?>
                    <img src="<?= $cover ?>" alt="" class="magazine-cover magazine-cover--card" loading="lazy">
                <?php else: ?>
                    <span class="magazine-cover magazine-cover--card magazine-cover--empty" aria-hidden="true"></span>
                <?php endif; ?>
            </a>
            <div class="magazine-issue-card__body">
                <h2 class="magazine-issue-card__title">
                    <?php if (!empty($row['est_hors_serie'])): ?>
                        <span class="badge">HS</span>
                    <?php endif; ?>
                    N° <?= Moncine\View::escape((string) ($row['numero'] ?? '')) ?>
                </h2>
                <p class="magazine-issue-card__meta hint">
                    <?= Moncine\View::escape($dateLabel) ?>
                    <?php if ($pages > 0): ?>
                        · <?= $pages ?> p.
                    <?php endif; ?>
                    <?php $issue = $row; require MONCINE_ROOT . '/templates/_magazine_support_tags.php'; ?>
                    <?php if (!$isWishlist && !Moncine\MagazineSupport::isPossessed($row)): ?>
                        <span class="magazine-tag magazine-tag--none">Non possédé</span>
                    <?php endif; ?>
                </p>
                <div class="magazine-issue-card__actions">
                    <a href="<?= Moncine\View::escape($issueUrl) ?>" class="btn btn-secondary btn-sm">Voir la fiche</a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
