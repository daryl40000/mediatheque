<?php
/**
 * Bandeau de vignettes (affiches) pour un profil utilisateur.
 *
 * @var list<array<string, mixed>> $films
 * @var string $emptyHint
 * @var string $mediaDomain
 * @var int $targetUserId
 */
$films = $films ?? [];
$emptyHint = $emptyHint ?? 'Aucun film à afficher.';
$mediaDomain = Moncine\MediaDomain::normalize($mediaDomain ?? Moncine\MediaDomain::FILM);
$targetUserId = (int) ($targetUserId ?? 0);
?>
<?php if ($films === []): ?>
    <p class="hint"><?= Moncine\View::escape($emptyHint) ?></p>
<?php else: ?>
    <ul class="social-poster-strip" role="list">
        <?php foreach ($films as $film):
            $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null);
            $titre = (string) ($film['titre'] ?? '');
            $annee = (int) ($film['annee'] ?? 0);
            $oeuvreId = (int) ($film['oeuvre_id'] ?? 0);
            $filmHref = $oeuvreId > 0
                ? Moncine\View::catalogOeuvreDetailUrlFromProfile($oeuvreId, $mediaDomain, $targetUserId)
                : '';
            ?>
            <li class="social-poster-strip__item" role="listitem">
                <?php if ($filmHref !== ''): ?>
                    <a href="<?= Moncine\View::escape($filmHref) ?>" class="social-poster-strip__link">
                <?php endif; ?>
                <figure class="social-poster-strip__card">
                    <?php if ($posterSrc !== ''): ?>
                        <img class="social-poster-strip__poster" src="<?= $posterSrc ?>"
                             alt="Affiche de <?= Moncine\View::escape($titre) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="social-poster-strip__placeholder" aria-hidden="true">?</span>
                    <?php endif; ?>
                    <figcaption class="social-poster-strip__caption">
                        <span class="social-poster-strip__title"><?= Moncine\View::escape($titre) ?></span>
                        <?php if ($annee > 0): ?>
                            <span class="social-poster-strip__year">(<?= $annee ?>)</span>
                        <?php endif; ?>
                    </figcaption>
                </figure>
                <?php if ($filmHref !== ''): ?>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
