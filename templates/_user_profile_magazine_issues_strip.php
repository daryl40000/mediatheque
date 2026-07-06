<?php
/**
 * Bandeau de vignettes pour les numéros magazines d’un profil public.
 *
 * @var list<array<string, mixed>> $issuesList
 * @var string $emptyHint
 * @var int $targetUserId
 */
$issuesList = $issuesList ?? [];
$emptyHint = $emptyHint ?? 'Aucun numéro à afficher.';
$targetUserId = (int) ($targetUserId ?? 0);
?>
<?php if ($issuesList === []): ?>
    <p class="hint"><?= Moncine\View::escape($emptyHint) ?></p>
<?php else: ?>
    <ul class="social-poster-strip" role="list">
        <?php foreach ($issuesList as $issue):
            $bibId = (int) ($issue['bib_id'] ?? 0);
            $oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
            $issueUrl = $oeuvreId > 0
                ? Moncine\View::catalogOeuvreDetailUrlFromProfile($oeuvreId, Moncine\MediaDomain::MAGAZINE, $targetUserId)
                : ($targetUserId > 0 && $bibId > 0
                    ? Moncine\View::userProfileMagazineIssueUrl($targetUserId, $bibId)
                    : '');
            $poster = trim((string) ($issue['poster_url'] ?? ''));
            $posterSrc = Moncine\View::posterSrc($poster !== '' ? $poster : null);
            $seriesTitre = (string) ($issue['series_titre'] ?? '');
            $numero = (string) ($issue['numero'] ?? '');
            $dateLabel = Moncine\PublicationType::formatParutionDate(
                (string) ($issue['date_parution'] ?? ''),
                (string) ($issue['publication_type'] ?? Moncine\PublicationType::MENSUEL)
            );
            ?>
            <li class="social-poster-strip__item" role="listitem">
                <?php if ($issueUrl !== ''): ?>
                    <a href="<?= Moncine\View::escape($issueUrl) ?>" class="social-poster-strip__link">
                <?php endif; ?>
                <figure class="social-poster-strip__card">
                    <?php if ($posterSrc !== ''): ?>
                        <img class="social-poster-strip__poster" src="<?= $posterSrc ?>"
                             alt="Couverture n°<?= Moncine\View::escape($numero) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="social-poster-strip__placeholder" aria-hidden="true">?</span>
                    <?php endif; ?>
                    <figcaption class="social-poster-strip__caption">
                        <span class="social-poster-strip__title"><?= Moncine\View::escape($seriesTitre) ?></span>
                        <span class="social-poster-strip__year">n°<?= Moncine\View::escape($numero) ?><?php if ($dateLabel !== '' && $dateLabel !== '—'): ?> · <?= Moncine\View::escape($dateLabel) ?><?php endif; ?></span>
                    </figcaption>
                </figure>
                <?php if ($issueUrl !== ''): ?>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
