<?php
/**
 * Fiche livre (bibliothèque).
 *
 * @var array<string, mixed>|null $book
 * @var int $bookId
 * @var bool $isWishlist
 * @var list<array<string, mixed>> $linkedGames
 * @var string $listBackUrl
 * @var int|null $monRessenti
 * @var list<array<string, mixed>> $readHistory
 * @var bool $everRead
 * @var string $readAtLabel
 * @var string $popoverOpen
 * @var string $editError
 * @var list<string> $knownCategories
 * @var list<string> $sagaSuggestions
 * @var array{foyer: list<array<string, mixed>>, friends: list<array<string, mixed>>} $socialRessentis
 * @var list<array<string, mixed>> $livreSagaNeighbors
 * @var string $sagaTitre
 */

$bookId = (int) ($bookId ?? 0);
$isWishlist = $isWishlist ?? false;
$listBackUrl = $listBackUrl ?? ($isWishlist ? '/livres-envies.php' : '/livres.php');
$linkedGames = $linkedGames ?? [];
$readHistory = $readHistory ?? [];
$everRead = (bool) ($everRead ?? false);
$readAtLabel = (string) ($readAtLabel ?? '');
$popoverOpen = (string) ($popoverOpen ?? '');
$editError = (string) ($editError ?? '');
$socialRessentis = $socialRessentis ?? ['foyer' => [], 'friends' => []];
?>
<section class="collection-page game-detail-page">
    <?php if ($book === null): ?>
        <h1>Livre introuvable</h1>
        <p class="hint">Ce livre n’existe pas ou n’est plus dans votre bibliothèque.</p>
        <p><a href="/livres.php" class="btn btn-secondary">← Mes livres</a></p>
    <?php else: ?>
        <?php
        $titre = (string) ($book['display_titre'] ?? $book['titre'] ?? 'Livre');
        $sousTitre = trim((string) ($book['sous_titre'] ?? ''));
        $sagaName = trim((string) ($book['saga'] ?? ''));
        $sagaOrdre = (int) ($book['saga_ordre'] ?? 0);
        ?>

        <p><a href="<?= Moncine\View::escape($listBackUrl) ?>" class="btn btn-secondary btn-sm">← Retour à la liste</a></p>

        <?php if (isset($_GET['added']) && (string) $_GET['added'] === '1'): ?>
            <div class="alert alert-success">Livre ajouté avec succès.</div>
        <?php endif; ?>
        <?php if (isset($_GET['saved']) && (string) $_GET['saved'] === '1'): ?>
            <div class="alert alert-success">Fiche enregistrée.</div>
        <?php endif; ?>
        <?php if (isset($_GET['promoted']) && (string) $_GET['promoted'] === '1'): ?>
            <div class="alert alert-success">Ce livre fait maintenant partie de votre collection.</div>
        <?php endif; ?>
        <?php if (isset($_GET['lu']) && (string) $_GET['lu'] === '1'): ?>
            <div class="alert alert-success">
                Lecture enregistrée<?php if (!empty($_GET['lu_date'])): ?>
                    (<?= Moncine\View::escape((string) $_GET['lu_date']) ?>)
                <?php endif; ?>.
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['lu_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['lu_error']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['note']) && Moncine\RessentiNote::normalizeScore((int) $_GET['note']) !== null): ?>
            <div class="alert alert-success">Ressenti enregistré : <?= Moncine\View::escape(Moncine\View::ressentiLabel((int) $_GET['note'])) ?>.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['note_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['note_error']) ?></p>
        <?php endif; ?>
        <?php if (!empty($_GET['promote_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['promote_error']) ?></p>
        <?php endif; ?>
        <?php if (!empty($_GET['delete_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['delete_error']) ?></p>
        <?php endif; ?>
        <?php if ($editError !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($editError) ?></p>
        <?php endif; ?>

        <?php if ($isWishlist): ?>
            <p class="hint film-wishlist-badge">Ce livre est dans vos envies (pas encore dans votre collection).</p>
        <?php endif; ?>

        <article class="film-detail game-detail film-detail--with-poster">
            <?php require MONCINE_ROOT . '/templates/_livre_detail_sidebar.php'; ?>

            <div class="film-detail__body game-detail__body">
                <header class="film-detail__heading game-detail__heading">
                    <h1 class="game-detail__title-row">
                        <span><?= Moncine\View::escape($titre) ?></span>
                        <?php if ((int) ($book['annee'] ?? 0) > 0): ?>
                            <span class="film-year">(<?= (int) $book['annee'] ?>)</span>
                        <?php endif; ?>
                        <?php if (!$isWishlist || !empty($monRessenti)): ?>
                            <?php require MONCINE_ROOT . '/templates/_game_detail_ressenti_title.php'; ?>
                        <?php endif; ?>
                    </h1>
                    <?php if ($sousTitre !== ''): ?>
                        <p class="film-original-title livre-subtitle"><?= Moncine\View::escape($sousTitre) ?></p>
                    <?php endif; ?>
                    <?php if ($sagaName !== ''): ?>
                        <p class="game-detail__saga">
                            <span class="game-detail__saga-label">Saga</span>
                            <?php if ($sagaOrdre > 0): ?>
                                <span class="saga-ordre"><?= $sagaOrdre ?>.</span>
                            <?php endif; ?>
                            <a href="<?= Moncine\View::escape(Moncine\View::sagasLivresUrl($sagaName)) ?>"
                               class="saga-link"
                               title="Voir tous les livres de la saga « <?= Moncine\View::escape($sagaName) ?> »">
                                <?= Moncine\View::escape($sagaName) ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <?php
                    $labelPrefix = 'Catégories';
                    require MONCINE_ROOT . '/templates/_livre_categories_display.php';
                    ?>
                </header>

                <div class="game-detail-facts-grid">
                    <dl class="film-facts game-detail-facts-grid__col">
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
                        <?php if ((int) ($book['pages'] ?? 0) > 0): ?>
                            <dt>Pages</dt>
                            <dd><?= (int) $book['pages'] ?></dd>
                        <?php endif; ?>
                        <?php if (trim((string) ($book['collection_label'] ?? '')) !== ''): ?>
                            <dt>Collection</dt>
                            <dd><?= Moncine\View::escape((string) $book['collection_label']) ?></dd>
                        <?php endif; ?>
                        <?php if (trim((string) ($book['langue'] ?? '')) !== ''): ?>
                            <dt>Langue</dt>
                            <dd><?= Moncine\View::escape((string) $book['langue']) ?></dd>
                        <?php endif; ?>
                        <?php if (trim((string) ($book['support_physique'] ?? '')) !== ''): ?>
                            <dt>Support</dt>
                            <dd><?= Moncine\View::escape((string) $book['support_physique']) ?></dd>
                        <?php endif; ?>
                        <?php if (!$isWishlist && $everRead && $readAtLabel !== ''): ?>
                            <dt>Dernière lecture</dt>
                            <dd><?= Moncine\View::escape($readAtLabel) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>

                <?php if (trim((string) ($book['synopsis'] ?? '')) !== ''): ?>
                    <section class="game-detail__synopsis" aria-labelledby="livre-synopsis-heading">
                        <h2 id="livre-synopsis-heading" class="game-detail__section-title">Résumé</h2>
                        <p><?= nl2br(Moncine\View::escape((string) $book['synopsis'])) ?></p>
                    </section>
                <?php endif; ?>

                <?php if (!empty($livreSagaNeighbors)): ?>
                    <?php require MONCINE_ROOT . '/templates/_livre_saga_context_strip.php'; ?>
                <?php endif; ?>

                <?php if ($linkedGames !== []): ?>
                    <section class="game-detail__related" aria-labelledby="livre-games-heading">
                        <h2 id="livre-games-heading" class="game-detail__section-title">Jeux liés</h2>
                        <ul class="livre-linked-games" role="list">
                            <?php foreach ($linkedGames as $linkedGame): ?>
                                <?php
                                $gameOeuvreId = (int) ($linkedGame['oeuvre_id'] ?? 0);
                                $gameTitle = (string) ($linkedGame['display_titre'] ?? $linkedGame['titre'] ?? '');
                                if ($gameOeuvreId <= 0 || $gameTitle === '') {
                                    continue;
                                }
                                ?>
                                <li role="listitem">
                                    <a href="<?= Moncine\View::escape(Moncine\View::oeuvreJeuUrl($gameOeuvreId)) ?>">
                                        <?= Moncine\View::escape($gameTitle) ?>
                                    </a>
                                    <?php if ((int) ($linkedGame['annee'] ?? 0) > 0): ?>
                                        <span class="hint">(<?= (int) $linkedGame['annee'] ?>)</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if ($isWishlist): ?>
                    <section class="film-promote-panel">
                        <h2 class="film-promote-panel__title">Ajouter à ma collection</h2>
                        <p class="hint">Vous avez acquis ce livre ? Il passera dans « Mes livres ».</p>
                        <form method="post" action="/promouvoir-livre-collection.php" class="inline-form">
                            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                            <input type="hidden" name="book_id" value="<?= $bookId ?>">
                            <input type="hidden" name="return" value="fiche">
                            <button type="submit" class="btn btn-primary">Ajouter à ma collection</button>
                        </form>
                    </section>
                <?php endif; ?>

                <div class="result-actions result-actions--with-delete">
                    <a href="<?= Moncine\View::escape(Moncine\View::livreEditUrl($bookId)) ?>" class="btn btn-secondary">Modifier</a>
                    <a href="<?= Moncine\View::escape($listBackUrl) ?>" class="btn btn-ghost">Retour à la liste</a>
                    <?php
                    $deleteTitle = $isWishlist ? 'Retirer des envies' : 'Supprimer de mes livres';
                    $deleteConfirm = $isWishlist
                        ? 'Retirer « ' . $titre . ' » de vos envies ?'
                        : 'Supprimer définitivement « ' . $titre . ' » de votre bibliothèque ?';
                    ?>
                    <form method="post" action="/supprimer-livre.php" class="inline-form game-detail__delete-form"
                          onsubmit="return confirm(<?= json_encode($deleteConfirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="book_id" value="<?= $bookId ?>">
                        <button type="submit"
                                class="btn btn-icon btn-danger-text btn-sm"
                                title="<?= Moncine\View::escape($deleteTitle) ?>"
                                aria-label="<?= Moncine\View::escape($deleteTitle) ?>">
                            <svg class="icon-trash" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </article>
    <?php endif; ?>
</section>
