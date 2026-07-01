<?php
/**
 * @var array<string, mixed>|null $profileUser
 * @var string $accessDenied
 * @var int $targetUserId
 * @var string $profileDomain
 * @var array<string, mixed>|null $tome
 * @var string $listMode
 * @var string $possessionLabel
 * @var string $supportLabel
 */
?>
<section class="account-page social-profile-page">
    <?php require MONCINE_ROOT . '/templates/_user_profile_domain_tabs.php'; ?>

    <?php if ($accessDenied !== '' || $profileUser === null || $tome === null): ?>
        <h1>Tome BD</h1>
        <p class="alert alert-warning"><?= Moncine\View::escape($accessDenied !== '' ? $accessDenied : 'Tome introuvable.') ?></p>
        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::BD)) ?>">← Retour au profil</a>
        </p>
    <?php else: ?>
        <?php
        $seriesId = (int) ($tome['series_id'] ?? 0);
        $cover = Moncine\View::posterSrc(trim((string) ($tome['poster_url'] ?? '')) ?: null);
        $displayName = Moncine\UserProfile::displayName($profileUser);
        $seriesUrl = Moncine\View::userProfileBdSeriesUrl($targetUserId, $seriesId, $listMode);
        $tomeNumero = (int) ($tome['tome_numero'] ?? 0);
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::BD)) ?>">
                <?= Moncine\View::escape($displayName) ?>
            </a>
            <span aria-hidden="true"> › </span>
            <a href="<?= Moncine\View::escape($seriesUrl) ?>">
                <?= Moncine\View::escape((string) ($tome['series_titre'] ?? 'Série')) ?>
            </a>
            <span aria-hidden="true"> › </span>
            <span><?= $tomeNumero > 0 ? 'Tome ' . $tomeNumero : Moncine\View::escape((string) ($tome['tome_label'] ?? 'Tome')) ?></span>
        </p>

        <p>
            <a href="<?= Moncine\View::escape($seriesUrl) ?>" class="btn btn-secondary btn-sm">
                ← <?= Moncine\View::escape((string) ($tome['series_titre'] ?? 'Série')) ?>
            </a>
        </p>

        <div class="magazine-issue-layout">
            <div class="magazine-issue-layout__cover">
                <?php if ($cover !== ''): ?>
                    <img src="<?= $cover ?>" alt="Couverture" class="magazine-cover film-poster--large film-poster--bd">
                <?php else: ?>
                    <div class="magazine-cover magazine-cover--empty" aria-hidden="true"></div>
                <?php endif; ?>
            </div>
            <div class="magazine-issue-layout__main">
                <h1><?= Moncine\View::escape((string) ($tome['display_titre'] ?? $tome['titre'] ?? '')) ?></h1>
                <p class="lead">
                    <?php if ($tomeNumero > 0): ?>
                        Tome <strong><?= $tomeNumero ?></strong>
                    <?php endif; ?>
                    <?php if ((int) ($tome['annee'] ?? 0) > 0): ?>
                        · <?= (int) $tome['annee'] ?>
                    <?php endif; ?>
                    <?php if ($supportLabel !== ''): ?>
                        · <?= Moncine\View::escape($supportLabel) ?>
                    <?php endif; ?>
                    <?php if (!Moncine\BdPossession::isPossessed($tome)): ?>
                        <span class="magazine-tag magazine-tag--none"><?= Moncine\View::escape($possessionLabel) ?></span>
                    <?php endif; ?>
                </p>
                <p class="hint">Fiche en lecture seule — collection de <?= Moncine\View::escape($displayName) ?>.</p>

                <?php if (trim((string) ($tome['scenariste'] ?? '')) !== '' || trim((string) ($tome['dessinateur'] ?? '')) !== ''): ?>
                    <dl class="film-facts">
                        <?php if (trim((string) ($tome['scenariste'] ?? '')) !== ''): ?>
                            <div><dt>Scénariste</dt><dd><?= Moncine\View::escape((string) $tome['scenariste']) ?></dd></div>
                        <?php endif; ?>
                        <?php if (trim((string) ($tome['dessinateur'] ?? '')) !== ''): ?>
                            <div><dt>Dessinateur</dt><dd><?= Moncine\View::escape((string) $tome['dessinateur']) ?></dd></div>
                        <?php endif; ?>
                    </dl>
                <?php endif; ?>
            </div>
        </div>

        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape($seriesUrl) ?>">← Retour à la série</a>
            ·
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::BD)) ?>">Profil de <?= Moncine\View::escape($displayName) ?></a>
        </p>
    <?php endif; ?>
</section>
