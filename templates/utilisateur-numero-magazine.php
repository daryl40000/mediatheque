<?php
/**
 * @var array<string, mixed>|null $profileUser
 * @var string $accessDenied
 * @var int $targetUserId
 * @var string $profileDomain
 * @var array<string, mixed>|null $issue
 * @var string $listMode
 * @var string $dateLabel
 */
?>
<section class="account-page social-profile-page">
    <?php require MONCINE_ROOT . '/templates/_user_profile_domain_tabs.php'; ?>

    <?php if ($accessDenied !== '' || $profileUser === null || $issue === null): ?>
        <h1>Numéro magazine</h1>
        <p class="alert alert-warning"><?= Moncine\View::escape($accessDenied !== '' ? $accessDenied : 'Numéro introuvable.') ?></p>
        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::MAGAZINE)) ?>">← Retour au profil</a>
        </p>
    <?php else: ?>
        <?php
        $bibId = (int) ($issue['bib_id'] ?? 0);
        $seriesId = (int) ($issue['series_id'] ?? 0);
        $cover = Moncine\View::posterSrc(trim((string) ($issue['poster_url'] ?? '')) ?: null);
        $displayName = Moncine\UserProfile::displayName($profileUser);
        $seriesUrl = Moncine\View::userProfileMagazineSeriesUrl($targetUserId, $seriesId, $listMode);
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::MAGAZINE)) ?>">
                <?= Moncine\View::escape($displayName) ?>
            </a>
            <span aria-hidden="true"> › </span>
            <a href="<?= Moncine\View::escape($seriesUrl) ?>">
                <?= Moncine\View::escape((string) ($issue['series_titre'] ?? 'Série')) ?>
            </a>
            <span aria-hidden="true"> › </span>
            <span>N° <?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?></span>
        </p>

        <p>
            <a href="<?= Moncine\View::escape($seriesUrl) ?>" class="btn btn-secondary btn-sm">
                ← <?= Moncine\View::escape((string) ($issue['series_titre'] ?? 'Série')) ?>
            </a>
        </p>

        <div class="magazine-issue-layout">
            <div class="magazine-issue-layout__cover">
                <?php if ($cover !== ''): ?>
                    <img src="<?= $cover ?>" alt="Couverture" class="magazine-cover">
                <?php else: ?>
                    <div class="magazine-cover magazine-cover--empty" aria-hidden="true"></div>
                <?php endif; ?>
            </div>
            <div class="magazine-issue-layout__main">
                <h1><?= Moncine\View::escape((string) ($issue['series_titre'] ?? '')) ?></h1>
                <p class="lead">
                    Numéro <strong><?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?></strong>
                    · <?= Moncine\View::escape($dateLabel) ?>
                    <?php if ((int) ($issue['pages'] ?? 0) > 0): ?>
                        · <?= (int) $issue['pages'] ?> p.
                    <?php endif; ?>
                    <?php require MONCINE_ROOT . '/templates/_magazine_support_tags.php'; ?>
                    <?php if (($issue['statut'] ?? '') === Moncine\LibraryStatut::COLLECTION && !Moncine\MagazineSupport::isPossessed($issue)): ?>
                        <span class="magazine-tag magazine-tag--none">Non possédé</span>
                    <?php endif; ?>
                </p>
                <p class="hint">Fiche en lecture seule — collection de <?= Moncine\View::escape($displayName) ?>.</p>

                <section class="magazine-sommaire">
                    <h2>Sommaire</h2>
                    <?php if (trim((string) ($issue['sommaire'] ?? '')) !== ''): ?>
                        <div class="magazine-sommaire__body"><?= Moncine\View::escape((string) $issue['sommaire']) ?></div>
                    <?php else: ?>
                        <p class="hint">Aucun sommaire renseigné.</p>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape($seriesUrl) ?>">← Retour à la série</a>
            ·
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::MAGAZINE)) ?>">Profil de <?= Moncine\View::escape($displayName) ?></a>
        </p>
    <?php endif; ?>
</section>
