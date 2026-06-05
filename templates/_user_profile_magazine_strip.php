<?php
/**
 * Bandeau de vignettes pour les séries magazines d’un profil public.
 *
 * @var list<array<string, mixed>> $seriesList
 * @var string $emptyHint
 * @var int $targetUserId
 * @var string $magazineListMode
 */
$seriesList = $seriesList ?? [];
$emptyHint = $emptyHint ?? 'Aucune série à afficher.';
$targetUserId = (int) ($targetUserId ?? 0);
$magazineListMode = (string) ($magazineListMode ?? 'collection');
?>
<?php if ($seriesList === []): ?>
    <p class="hint"><?= Moncine\View::escape($emptyHint) ?></p>
<?php else: ?>
    <ul class="social-poster-strip" role="list">
        <?php foreach ($seriesList as $series):
            $seriesId = (int) ($series['id'] ?? 0);
            $seriesUrl = $targetUserId > 0 && $seriesId > 0
                ? Moncine\View::userProfileMagazineSeriesUrl($targetUserId, $seriesId, $magazineListMode)
                : '';
            $poster = trim((string) ($series['poster_url'] ?? $series['latest_poster_url'] ?? ''));
            $posterSrc = Moncine\View::posterSrc($poster !== '' ? $poster : null);
            $titre = (string) ($series['titre'] ?? '');
            $issueCount = (int) ($series['issue_count'] ?? 0);
            ?>
            <li class="social-poster-strip__item" role="listitem">
                <?php if ($seriesUrl !== ''): ?>
                    <a href="<?= Moncine\View::escape($seriesUrl) ?>" class="social-poster-strip__link">
                <?php endif; ?>
                <figure class="social-poster-strip__card">
                    <?php if ($posterSrc !== ''): ?>
                        <img class="social-poster-strip__poster" src="<?= $posterSrc ?>"
                             alt="Couverture de <?= Moncine\View::escape($titre) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="social-poster-strip__placeholder" aria-hidden="true">?</span>
                    <?php endif; ?>
                    <figcaption class="social-poster-strip__caption">
                        <span class="social-poster-strip__title"><?= Moncine\View::escape($titre) ?></span>
                        <?php if ($issueCount > 0): ?>
                            <span class="social-poster-strip__year"><?= $issueCount ?> num.</span>
                        <?php endif; ?>
                    </figcaption>
                </figure>
                <?php if ($seriesUrl !== ''): ?>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
