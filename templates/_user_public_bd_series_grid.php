<?php
/**
 * Grille lecture seule des séries BD d’un autre utilisateur.
 *
 * @var list<array<string, mixed>> $listBdSeries
 * @var int $targetUserId
 * @var string $listMode
 * @var string $sortBy
 * @var string $sortDir
 * @var string $profileDomain
 */
$sortLink = static function (string $label, string $column) use (
    $targetUserId,
    $listMode,
    $sortBy,
    $sortDir,
    $profileDomain
): void {
    $active = $sortBy === $column;
    ?>
    <a href="<?= Moncine\View::escape(
        Moncine\View::userProfileListUrl(
            $targetUserId,
            $listMode,
            $column,
            $sortBy,
            $sortDir,
            null,
            $profileDomain
        )
    ) ?>"
       class="collection-grid-sort__link<?= $active ? ' is-active' : '' ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
?>
<?php if ($listBdSeries === []): ?>
    <p class="hint">Aucune série dans cette liste.</p>
<?php else: ?>
    <p class="stats"><?= count($listBdSeries) ?> série<?= count($listBdSeries) > 1 ? 's' : '' ?></p>
    <nav class="collection-grid-sort social-profile-list-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $sortLink('Titre', 'titre'); ?>
        <?php $sortLink('Tomes', 'tomes'); ?>
        <?php $sortLink('Type', 'kind'); ?>
    </nav>
    <div class="magazine-series-grid social-profile-grid">
        <?php foreach ($listBdSeries as $series): ?>
            <?php
            $seriesId = (int) ($series['id'] ?? 0);
            $seriesUrl = Moncine\View::userProfileBdSeriesUrl($targetUserId, $seriesId, $listMode);
            $poster = trim((string) ($series['poster_url'] ?? $series['latest_poster_url'] ?? ''));
            $posterSrc = Moncine\View::posterSrc($poster !== '' ? $poster : null);
            $possessedCount = (int) ($series['possessed_tome_count'] ?? $series['tome_count'] ?? 0);
            $catalogCount = (int) ($series['catalog_tome_count'] ?? 0);
            ?>
            <a href="<?= Moncine\View::escape($seriesUrl) ?>" class="magazine-series-card">
                <?php if ($posterSrc !== ''): ?>
                    <img src="<?= $posterSrc ?>" alt="" class="magazine-series-card__cover" loading="lazy">
                <?php else: ?>
                    <div class="magazine-series-card__cover magazine-series-card__cover--empty" aria-hidden="true"></div>
                <?php endif; ?>
                <div class="magazine-series-card__body">
                    <h2 class="magazine-series-card__title"><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h2>
                    <p class="hint">
                        <?= Moncine\View::escape((string) ($series['kind_label'] ?? '')) ?>
                        · <?= $possessedCount ?> possédé<?= $possessedCount > 1 ? 's' : '' ?> sur <?= $catalogCount ?>
                    </p>
                    <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                        <p class="hint"><?= Moncine\View::escape((string) $series['editeur']) ?></p>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
