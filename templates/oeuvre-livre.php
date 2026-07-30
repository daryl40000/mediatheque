<?php
/**
 * Fiche catalogue livre.
 *
 * @var array<string, mixed>|null $book
 * @var int $oeuvreId
 * @var list<array<string, mixed>> $linkedGames
 * @var int|null $libraryBibId
 */
$linkedGames = $linkedGames ?? [];
$libraryBibId = $libraryBibId ?? null;
?>
<section class="collection-page game-detail-page">
    <?php if ($book === null): ?>
        <h1>Livre introuvable</h1>
        <p class="hint">Cette fiche n’existe pas dans le catalogue.</p>
        <p><a href="/livres.php" class="btn btn-secondary">← Mes livres</a></p>
    <?php else: ?>
        <?php
        $posterSrc = Moncine\View::posterSrc($book['poster_url'] ?? null);
        $backCoverSrc = Moncine\View::posterSrc($book['back_cover_url'] ?? null);
        $titre = (string) ($book['display_titre'] ?? $book['titre'] ?? 'Livre');
        $sousTitre = trim((string) ($book['sous_titre'] ?? ''));
        $sagaName = trim((string) ($book['saga'] ?? ''));
        ?>
        <p><a href="/livres.php" class="btn btn-secondary btn-sm">← Mes livres</a></p>

        <article class="film-detail game-detail<?= ($posterSrc !== '' || $backCoverSrc !== '') ? ' film-detail--with-poster' : '' ?>">
            <aside class="game-detail-sidebar livre-detail-sidebar" aria-label="Couvertures">
                <?php if ($posterSrc !== ''): ?>
                    <img class="film-poster film-poster--large game-detail-sidebar__poster"
                         src="<?= $posterSrc ?>"
                         alt="Couverture de <?= Moncine\View::escape($titre) ?>">
                <?php else: ?>
                    <span class="film-poster film-poster--large film-poster--empty game-detail-sidebar__poster" aria-hidden="true"></span>
                <?php endif; ?>
                <?php if ($backCoverSrc !== ''): ?>
                    <figure class="livre-back-cover">
                        <button type="button"
                                class="livre-back-cover__open"
                                data-livre-cover-lightbox
                                data-cover-src="<?= $backCoverSrc ?>"
                                data-cover-alt="4e de couverture de <?= Moncine\View::escape($titre) ?>"
                                aria-label="Agrandir la 4e de couverture">
                            <img class="livre-back-cover__img"
                                 src="<?= $backCoverSrc ?>"
                                 alt="4e de couverture de <?= Moncine\View::escape($titre) ?>">
                        </button>
                        <figcaption class="hint livre-back-cover__caption">4e de couverture — cliquer pour agrandir</figcaption>
                    </figure>
                <?php endif; ?>
            </aside>

            <div class="film-detail__body game-detail__body">
                <header class="film-detail__heading game-detail__heading">
                    <h1 class="game-detail__title-row">
                        <span><?= Moncine\View::escape($titre) ?></span>
                        <?php if ((int) ($book['annee'] ?? 0) > 0): ?>
                            <span class="film-year">(<?= (int) $book['annee'] ?>)</span>
                        <?php endif; ?>
                    </h1>
                    <?php if ($sousTitre !== ''): ?>
                        <p class="film-original-title livre-subtitle"><?= Moncine\View::escape($sousTitre) ?></p>
                    <?php endif; ?>
                    <p class="hint">Fiche catalogue</p>
                    <?php if ($sagaName !== ''): ?>
                        <p class="game-detail__saga">
                            <span class="game-detail__saga-label">Saga</span>
                            <a href="<?= Moncine\View::escape(Moncine\View::sagasLivresUrl($sagaName)) ?>" class="saga-link">
                                <?= Moncine\View::escape($sagaName) ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <?php
                    $labelPrefix = 'Catégories';
                    require MONCINE_ROOT . '/templates/_livre_categories_display.php';
                    ?>
                </header>

                <dl class="film-facts">
                    <?php if (trim((string) ($book['auteur'] ?? '')) !== ''): ?>
                        <dt>Auteur</dt>
                        <dd><?= Moncine\View::escape((string) $book['auteur']) ?></dd>
                    <?php endif; ?>
                    <?php if (trim((string) ($book['editeur'] ?? '')) !== ''): ?>
                        <dt>Éditeur</dt>
                        <dd><?= Moncine\View::escape((string) $book['editeur']) ?></dd>
                    <?php endif; ?>
                    <?php if (trim((string) ($book['isbn'] ?? '')) !== ''): ?>
                        <dt>ISBN</dt>
                        <dd><?= Moncine\View::escape((string) $book['isbn']) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if (trim((string) ($book['synopsis'] ?? '')) !== ''): ?>
                    <section aria-labelledby="oeuvre-livre-synopsis">
                        <h2 id="oeuvre-livre-synopsis" class="game-detail__section-title">Résumé</h2>
                        <p><?= nl2br(Moncine\View::escape((string) $book['synopsis'])) ?></p>
                    </section>
                <?php endif; ?>

                <?php if ($linkedGames !== []): ?>
                    <section aria-labelledby="oeuvre-livre-games">
                        <h2 id="oeuvre-livre-games" class="game-detail__section-title">Jeux liés</h2>
                        <ul>
                            <?php foreach ($linkedGames as $linkedGame): ?>
                                <?php
                                $gameOeuvreId = (int) ($linkedGame['oeuvre_id'] ?? 0);
                                $gameTitle = (string) ($linkedGame['display_titre'] ?? $linkedGame['titre'] ?? '');
                                if ($gameOeuvreId <= 0 || $gameTitle === '') {
                                    continue;
                                }
                                ?>
                                <li>
                                    <a href="<?= Moncine\View::escape(Moncine\View::oeuvreJeuUrl($gameOeuvreId)) ?>">
                                        <?= Moncine\View::escape($gameTitle) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <div class="result-actions">
                    <?php if ($libraryBibId !== null && $libraryBibId > 0): ?>
                        <a href="<?= Moncine\View::escape(Moncine\View::livreUrl((int) $libraryBibId)) ?>" class="btn btn-primary">
                            Voir dans ma bibliothèque
                        </a>
                    <?php else: ?>
                        <a href="<?= Moncine\View::escape(Moncine\View::addLivreUrl()) ?>" class="btn btn-primary">
                            Ajouter un livre
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endif; ?>
</section>
