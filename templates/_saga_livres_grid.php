<?php
/**
 * Livres d’une saga — vignettes.
 *
 * @var list<array<string, mixed>> $books
 */
?>
<ul class="collection-grid collection-grid--poster-only" role="list">
    <?php foreach ($books as $book):
        $bibId = (int) ($book['bib_id'] ?? 0);
        $posterSrc = Moncine\View::posterSrc($book['poster_url'] ?? null);
        $livreUrl = Moncine\View::livreUrl($bibId);
        $annee = (int) ($book['annee'] ?? 0);
        $displayTitle = (string) ($book['display_titre'] ?? $book['titre'] ?? '');
        $sousTitre = trim((string) ($book['sous_titre'] ?? ''));
        $sagaOrdre = (int) ($book['saga_ordre'] ?? 0);
        $auteur = trim((string) ($book['auteur'] ?? ''));
        ?>
        <li class="collection-grid__item" role="listitem">
            <article class="collection-grid__card">
                <a href="<?= Moncine\View::escape($livreUrl) ?>" class="collection-grid__link">
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
                        <?php if ($sagaOrdre > 0): ?>
                            <p class="collection-grid__meta hint">Tome <?= $sagaOrdre ?></p>
                        <?php endif; ?>
                        <h3 class="collection-grid__title">
                            <?= Moncine\View::escape($displayTitle) ?>
                        </h3>
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
        </li>
    <?php endforeach; ?>
</ul>
