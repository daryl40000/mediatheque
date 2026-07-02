<?php
/**
 * @var array<string, mixed>|null $link
 * @var array<string, mixed>|null $series
 * @var list<array<string, mixed>> $tomes
 */
?>
<section class="collection-page share-visitor-page">
    <?php if ($link === null || $series === null): ?>
        <h1>Série introuvable</h1>
        <p class="hint">Ce lien ne permet pas d’afficher cette série.</p>
        <p><a href="<?= Moncine\View::escape((string) ($listUrl ?? '/partage-bd.php')) ?>" class="btn btn-secondary">← Retour à la liste</a></p>
    <?php else: ?>
        <?php
        $seriesId = (int) ($series['id'] ?? 0);
        $rawToken = (string) ($rawToken ?? '');
        $listContext = $listContext ?? [];
        $isWishlist = ($statut ?? '') === Moncine\LibraryStatut::WISHLIST;
        $posterSrc = Moncine\View::seriesPosterSrc($series);
        $tomeUrlForBibId = static fn (int $bibId): string => Moncine\ShareLinkService::bdAlbumUrl(
            $rawToken,
            $bibId,
            $listContext
        );
        ?>
        <p>
            <a href="<?= Moncine\View::escape((string) ($listUrl ?? '/partage-bd.php')) ?>" class="btn btn-secondary btn-sm">← Retour à la liste</a>
        </p>

        <header class="magazine-series-header">
            <div class="magazine-series-header__main">
                <?php if ($posterSrc !== ''): ?>
                    <img src="<?= $posterSrc ?>" alt="" class="magazine-cover magazine-cover--header">
                <?php endif; ?>
                <div>
                    <h1><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h1>
                    <p class="lead">
                        <?= Moncine\View::escape((string) ($kindLabel ?? '')) ?>
                        · Partagé par <strong><?= Moncine\View::escape((string) ($ownerLabel ?? '')) ?></strong>
                    </p>
                    <p class="hint">Lecture seule.</p>
                </div>
            </div>
        </header>

        <?php if ($tomes === []): ?>
            <p class="hint">Aucun tome dans cette série.</p>
        <?php else: ?>
            <p class="stats"><?= (int) ($totalCount ?? count($tomes)) ?> tome<?= (int) ($totalCount ?? count($tomes)) > 1 ? 's' : '' ?>.</p>
            <?php require MONCINE_ROOT . '/templates/_user_public_bd_tomes_grid.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
