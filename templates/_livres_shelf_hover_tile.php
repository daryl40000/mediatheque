<?php
/**
 * Tuile vignette affichée au survol d’une tranche livre (vue bibliothèque).
 *
 * @var array<string, mixed> $book
 * @var string $livreUrl
 * @var string $posterSrc
 * @var string $displayTitle
 * @var int $annee
 */
$annee = (int) ($annee ?? 0);
$posterSrc = (string) ($posterSrc ?? '');
$livreUrl = (string) ($livreUrl ?? Moncine\View::livreUrl((int) ($book['bib_id'] ?? 0)));
$auteur = trim((string) ($book['auteur'] ?? ''));
$sousTitre = trim((string) ($book['sous_titre'] ?? ''));
?>
<div class="game-shelf__preview" aria-hidden="true">
    <article class="collection-grid__card game-shelf__preview-card">
        <a href="<?= Moncine\View::escape($livreUrl) ?>" class="collection-grid__link" tabindex="-1">
            <div class="collection-grid__poster-wrap">
                <?php if ($posterSrc !== ''): ?>
                    <img class="collection-grid__poster" src="<?= $posterSrc ?>"
                         alt="Couverture de <?= Moncine\View::escape($displayTitle) ?>"
                         width="140" height="210" loading="lazy" decoding="async">
                <?php else: ?>
                    <span class="collection-grid__poster collection-grid__poster--empty"
                          aria-hidden="true"></span>
                <?php endif; ?>
            </div>
            <div class="collection-grid__caption">
                <h3 class="collection-grid__title"><?= Moncine\View::escape($displayTitle) ?></h3>
                <?php if ($sousTitre !== ''): ?>
                    <p class="collection-grid__meta hint"><?= Moncine\View::escape($sousTitre) ?></p>
                <?php endif; ?>
                <p class="collection-grid__meta">
                    <?php if ($auteur !== ''): ?>
                        <span><?= Moncine\View::escape($auteur) ?></span>
                    <?php endif; ?>
                    <?php if ($annee > 0): ?>
                        <span class="collection-grid__year"><?= $annee ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </a>
    </article>
</div>
