<?php
/**
 * Bandeau de vignettes pour les tomes BD d’un profil public.
 *
 * @var list<array<string, mixed>> $tomesList
 * @var string $emptyHint
 * @var int $targetUserId
 */
$tomesList = $tomesList ?? [];
$emptyHint = $emptyHint ?? 'Aucun tome à afficher.';
$targetUserId = (int) ($targetUserId ?? 0);
?>
<?php if ($tomesList === []): ?>
    <p class="hint"><?= Moncine\View::escape($emptyHint) ?></p>
<?php else: ?>
    <ul class="social-poster-strip" role="list">
        <?php foreach ($tomesList as $tome):
            $bibId = (int) ($tome['bib_id'] ?? $tome['id'] ?? 0);
            $oeuvreId = (int) ($tome['oeuvre_id'] ?? 0);
            $tomeUrl = $oeuvreId > 0
                ? Moncine\View::catalogOeuvreDetailUrlFromProfile($oeuvreId, Moncine\MediaDomain::BD, $targetUserId)
                : ($targetUserId > 0 && $bibId > 0
                    ? Moncine\View::userProfileBdAlbumUrl($targetUserId, $bibId)
                    : '');
            $poster = trim((string) ($tome['poster_url'] ?? ''));
            $posterSrc = Moncine\View::posterSrc($poster !== '' ? $poster : null);
            $seriesTitre = (string) ($tome['series_titre'] ?? '');
            $tomeNumero = (int) ($tome['tome_numero'] ?? 0);
            $tomeLabel = trim((string) ($tome['tome_label'] ?? '')) !== ''
                ? trim((string) ($tome['tome_label'] ?? ''))
                : Moncine\BdRowMapper::tomeSummary($tome);
            ?>
            <li class="social-poster-strip__item" role="listitem">
                <?php if ($tomeUrl !== ''): ?>
                    <a href="<?= Moncine\View::escape($tomeUrl) ?>" class="social-poster-strip__link">
                <?php endif; ?>
                <figure class="social-poster-strip__card">
                    <?php if ($posterSrc !== ''): ?>
                        <img class="social-poster-strip__poster" src="<?= $posterSrc ?>"
                             alt="Couverture <?= Moncine\View::escape($tomeLabel) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="social-poster-strip__placeholder" aria-hidden="true">?</span>
                    <?php endif; ?>
                    <figcaption class="social-poster-strip__caption">
                        <span class="social-poster-strip__title"><?= Moncine\View::escape($seriesTitre) ?></span>
                        <span class="social-poster-strip__year"><?= Moncine\View::escape($tomeLabel) ?></span>
                    </figcaption>
                </figure>
                <?php if ($tomeUrl !== ''): ?>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
