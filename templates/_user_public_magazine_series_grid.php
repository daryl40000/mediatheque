<?php
/**
 * Grille lecture seule des séries magazines d’un autre utilisateur.
 *
 * @var list<array<string, mixed>> $listMagazineSeries
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
<?php if ($listMagazineSeries === []): ?>
    <p class="hint">Aucune série dans cette liste.</p>
<?php else: ?>
    <p class="stats"><?= count($listMagazineSeries) ?> série<?= count($listMagazineSeries) > 1 ? 's' : '' ?></p>
    <nav class="collection-grid-sort social-profile-list-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $sortLink('Titre', 'titre'); ?>
        <?php $sortLink('Numéros', 'issues'); ?>
        <?php $sortLink('Dernière parution', 'last_date'); ?>
    </nav>
    <div class="magazine-series-grid social-profile-grid">
        <?php foreach ($listMagazineSeries as $series): ?>
            <?php
            $seriesId = (int) ($series['id'] ?? 0);
            $seriesUrl = Moncine\View::userProfileMagazineSeriesUrl($targetUserId, $seriesId, $listMode);
            $poster = trim((string) ($series['poster_url'] ?? $series['latest_poster_url'] ?? ''));
            $posterSrc = Moncine\View::posterSrc($poster !== '' ? $poster : null);
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
                        <?= Moncine\View::escape(Moncine\PublicationType::label((string) ($series['publication_type'] ?? ''))) ?>
                        · <?= (int) ($series['issue_count'] ?? 0) ?> numéro(s)
                    </p>
                    <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                        <p class="hint"><?= Moncine\View::escape((string) $series['editeur']) ?></p>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
