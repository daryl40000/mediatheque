<?php
/** @var array<string, mixed>|null $film */
?>
<section>
    <?php if ($film === null): ?>
        <h1>Film introuvable</h1>
        <p>Ce film n’existe pas ou a été supprimé.</p>
        <a href="/films.php" class="btn btn-primary">Retour à mes films</a>
    <?php else:
        $filmId = (int) $film['id'];
        $isWishlist = ($film['statut'] ?? '') === Moncine\LibraryStatut::WISHLIST;
        ?>
        <?php if (isset($_GET['vu'])): ?>
            <p class="alert alert-success">
                Vision enregistrée<?= !empty($_GET['vu_date'])
                    ? ' le ' . Moncine\View::escape((string) $_GET['vu_date'])
                    : '' ?><?php if (!empty($_GET['vu_note'])):
                    $vuScore = Moncine\RessentiNote::normalizeScore((int) $_GET['vu_note']);
                    if ($vuScore !== null): ?>
                    — ressenti : <?= Moncine\View::escape(Moncine\View::ressentiLabel($vuScore)) ?>
                    <?php endif; endif ?>.
            </p>
        <?php endif; ?>
        <?php if (!empty($_GET['vu_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['vu_error']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['vision_supprimee'])): ?>
            <p class="alert alert-success">Date de vision supprimée de l’historique.</p>
        <?php endif; ?>
        <?php if (!empty($_GET['vision_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['vision_error']) ?></p>
        <?php endif; ?>
        <?php if (!empty($_GET['delete_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['delete_error']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['promoted']) && (string) $_GET['promoted'] === '1'): ?>
            <p class="alert alert-success">Ce film fait maintenant partie de vos films.</p>
        <?php endif; ?>
        <?php if (!empty($_GET['promote_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['promote_error']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['added']) && (string) $_GET['added'] === '1'): ?>
            <p class="alert alert-success">Film ajouté avec succès.</p>
        <?php endif; ?>
        <?php if (!empty($saved)): ?>
            <p class="alert alert-success">Fiche enregistrée.</p>
        <?php endif; ?>

        <?php if ($isWishlist): ?>
            <p class="film-detail-page__toolbar">
                <a href="<?= Moncine\View::escape(Moncine\View::addFilmUrl(Moncine\LibraryStatut::WISHLIST)) ?>"
                   class="btn btn-primary">
                    Ajouter un film
                </a>
            </p>
        <?php endif; ?>

        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape($listBackUrl ?? ($isWishlist ? '/souhaits.php' : '/films.php')) ?>">
                <?= $isWishlist
                    ? Moncine\View::escape(Moncine\LibraryStatut::label(Moncine\LibraryStatut::WISHLIST))
                    : Moncine\View::escape(Moncine\LibraryStatut::label(Moncine\LibraryStatut::COLLECTION)) ?>
            </a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape($film['titre']) ?></span>
        </p>

        <div id="film-list-nav" class="film-list-nav-anchor">
            <?php require MONCINE_ROOT . '/templates/_film_list_nav.php'; ?>
        </div>
        <?php if ($isWishlist): ?>
            <p class="hint film-wishlist-badge">Ce film est dans vos envies (pas encore dans vos films).</p>
            <?php if (Moncine\WishlistTargetRepository::tableExists()): ?>
                <?php
                $filmId = $filmId ?? (int) ($film['id'] ?? 0);
                require MONCINE_ROOT . '/templates/_wishlist_targets_panel.php';
                ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null); ?>
        <article id="film-detail" class="film-detail<?= $posterSrc !== '' ? ' film-detail--with-poster' : '' ?>">
            <?php if ($posterSrc !== ''): ?>
                <img class="film-poster film-poster--large" src="<?= $posterSrc ?>"
                     alt="Affiche de <?= Moncine\View::escape($film['titre']) ?>">
            <?php endif; ?>

            <div class="film-detail__body">
                <header class="film-detail__heading">
                    <h1>
                        <?= Moncine\View::escape($film['titre']) ?>
                        <?php if ((int) ($film['annee'] ?? 0) > 0): ?>
                            <span class="film-year">(<?= (int) $film['annee'] ?>)</span>
                        <?php endif; ?>
                    </h1>
                    <?php if (trim((string) ($film['titre_original'] ?? '')) !== ''): ?>
                        <p class="film-original-title" lang="und">
                            <?= Moncine\View::escape((string) $film['titre_original']) ?>
                        </p>
                    <?php endif; ?>
                </header>

                <?php if (!empty($monRessenti)): ?>
                    <p class="film-detail__ressenti">
                        <?php
                        $score = (int) $monRessenti;
                        $showLabel = true;
                        $size = 'default';
                        require MONCINE_ROOT . '/templates/_ressenti_badge.php';
                        ?>
                    </p>
                <?php endif; ?>

                <dl class="film-facts">
                    <dt>Réalisateur</dt>
                    <dd><?php $name = (string) ($film['realisateur'] ?? ''); require MONCINE_ROOT . '/templates/_personne_link.php'; ?></dd>

                    <?php
                    $acteurs = Moncine\FilmManualEdit::acteursList($film);
                    if ($acteurs !== []):
                        ?>
                        <dt>Acteurs principaux</dt>
                        <dd class="personnes-list">
                            <?php foreach ($acteurs as $i => $acteurName): ?>
                                <?php if ($i > 0): ?><span class="personnes-sep">, </span><?php endif; ?>
                                <?php $name = $acteurName; require MONCINE_ROOT . '/templates/_personne_link.php'; ?>
                            <?php endforeach; ?>
                        </dd>
                    <?php endif; ?>

                    <dt>Catégorie</dt>
                    <dd><?= Moncine\View::escape(Moncine\View::contentKindLabel($film)) ?></dd>

                    <?php if (Moncine\MoncineContentKind::isSerie((string) ($film['moncine_kind'] ?? ''))): ?>
                        <dt>Saison</dt>
                        <dd><?php
                            $saisonLabel = trim((string) ($film['saison_label'] ?? ''));
                            $saisonNum = (int) ($film['saison_numero'] ?? 0);
                            if ($saisonLabel !== '') {
                                echo Moncine\View::escape($saisonLabel);
                            } elseif ($saisonNum > 0) {
                                echo 'Saison ' . $saisonNum;
                            } else {
                                echo '—';
                            }
                            ?></dd>
                    <?php endif; ?>

                    <dt>Année</dt>
                    <dd><?= Moncine\View::escape(Moncine\FilmRepository::formatAnnee((int) ($film['annee'] ?? 0))) ?></dd>

                    <dt>Nationalité</dt>
                    <dd><?= Moncine\View::escape(
                        Moncine\FilmRepository::formatNationalite((string) ($film['nationalite'] ?? ''))
                    ) ?></dd>

                    <dt>Durée</dt>
                    <dd><?= Moncine\View::escape(Moncine\FilmRepository::formatDuree((int) ($film['duree_min'] ?? 0))) ?></dd>

                    <dt>Style</dt>
                    <dd><?= ($film['styles'] ?? '') !== '' ? Moncine\View::escape($film['styles']) : '—' ?></dd>

                    <dt>Saga</dt>
                    <dd><?php
                        $sagaName = (string) ($film['saga'] ?? '');
                        $sagaOrdre = (int) ($film['saga_ordre'] ?? 0);
                        require MONCINE_ROOT . '/templates/_saga_link.php';
                        ?></dd>

                    <dt>Format image</dt>
                    <dd><?= ($film['format_image'] ?? '') !== '' ? Moncine\View::escape($film['format_image']) : '—' ?></dd>

                    <dt>Bande sonore</dt>
                    <dd><?= ($film['format_son'] ?? '') !== '' ? Moncine\View::escape($film['format_son']) : '—' ?></dd>

                    <dt>Support physique</dt>
                    <dd><?php $supportKey = (string) ($film['support_physique'] ?? ''); require MONCINE_ROOT . '/templates/_support_link.php'; ?></dd>

                    <?php if (trim((string) ($film['ean'] ?? '')) !== ''): ?>
                        <dt>Code-barres (EAN)</dt>
                        <dd class="film-ean">
                            <span class="film-ean__code"><?= Moncine\View::escape(Moncine\View::formatEan((string) $film['ean'])) ?></span>
                        </dd>
                    <?php endif; ?>

                    <dt>Vision la plus récente</dt>
                    <dd><?= !empty($derniereVue)
                        ? Moncine\View::escape(Moncine\HistoriqueRepository::formatDateVue((string) $derniereVue))
                        : 'Jamais' ?></dd>

                    <?php if ((int) ($film['tmdb_id'] ?? 0) > 0): ?>
                        <dt>Identifiant TMDB</dt>
                        <dd>
                            <?php
                            $tmdbUrl = Moncine\TmdbMediaType::publicUrl(
                                (int) $film['tmdb_id'],
                                (string) ($film['tmdb_media_type'] ?? '')
                            );
                            ?>
                            <a href="<?= Moncine\View::escape($tmdbUrl) ?>"
                               target="_blank" rel="noopener">
                                <?= Moncine\View::escape(Moncine\TmdbMediaType::label(
                                    (string) ($film['tmdb_media_type'] ?? ''),
                                    (string) ($film['tmdb_tv_kind'] ?? '')
                                )) ?>
                                #<?= (int) $film['tmdb_id'] ?>
                            </a>
                        </dd>
                    <?php endif; ?>
                </dl>

                <?php if (!empty($film['synopsis'])): ?>
                    <h2>Synopsis</h2>
                    <p class="film-synopsis"><?= Moncine\View::escape($film['synopsis']) ?></p>
                <?php endif; ?>

                <?php if (!$isWishlist): ?>
                    <?php require MONCINE_ROOT . '/templates/_ressenti_social_panel.php'; ?>
                <?php endif; ?>

                <?php if (!empty($viewings)): ?>
                    <h2>Historique des visions</h2>
                    <p class="hint">Supprimez les dates erronées avec l’icône poubelle à droite.</p>
                    <ul class="viewings-list">
                        <?php foreach ($viewings as $view):
                            $viewId = (int) ($view['id'] ?? 0);
                            $vDate = Moncine\HistoriqueRepository::formatDateVue((string) ($view['date_vue'] ?? ''));
                            $vNote = $view['note'] ?? null;
                            ?>
                            <li class="viewings-list__item">
                                <span class="viewings-list__info">
                                    <?= Moncine\View::escape($vDate) ?>
                                    <?php if ($vNote !== null && $vNote !== ''): ?>
                                        <?php
                                        $score = (int) $vNote;
                                        $showLabel = false;
                                        $size = 'small';
                                        require MONCINE_ROOT . '/templates/_ressenti_badge.php';
                                        ?>
                                    <?php endif; ?>
                                </span>
                                <?php if ($viewId > 0): ?>
                                    <form method="post" action="/supprimer-vision.php" class="inline-form viewings-list__delete"
                                          onsubmit="return confirm('Supprimer la vision du <?= Moncine\View::escape($vDate) ?> ?');">
                                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                        <input type="hidden" name="film_id" value="<?= $filmId ?>">
                                        <input type="hidden" name="historique_id" value="<?= $viewId ?>">
                                        <button type="submit" class="btn btn-icon btn-danger-text"
                                                title="Supprimer cette vision"
                                                aria-label="Supprimer la vision du <?= Moncine\View::escape($vDate) ?>">
                                            <svg class="icon-trash" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                                            </svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php
                $editOpen = $editOpen ?? false;
                $saveError = $saveError ?? '';
                $canManageCatalog = $canManageCatalog ?? false;
                require MONCINE_ROOT . '/templates/_film_edit_form.php';
                ?>

                <?php if (!empty($showTmdbEnrich)): ?>
                    <?php require MONCINE_ROOT . '/templates/_enrich_film_panel.php'; ?>
                <?php endif; ?>

                <?php if ($isWishlist): ?>
                    <section class="film-promote-panel">
                        <h2 class="film-promote-panel__title">Ajouter à mes films</h2>
                        <p class="hint">Vous avez acheté ce film ? Il passera dans « Mes films ».</p>
                        <?php
                        $wishlistTargets = $wishlistTargets ?? [];
                        $includeListContext = true;
                        require MONCINE_ROOT . '/templates/_film_promote_wishlist_form.php';
                        ?>
                    </section>
                <?php else: ?>
                <section class="marquer-vu-panel">
                    <h2 class="marquer-vu-panel__title">Enregistrer une vision</h2>
                    <?php
                    $return = 'film';
                    $submitLabel = $everSeen ? 'Ajouter cette date' : 'Marquer comme vu';
                    $defaultNote = !empty($monRessenti) ? (int) $monRessenti : null;
                    require MONCINE_ROOT . '/templates/_marquer_vu_form.php';
                    ?>
                </section>
                <?php endif; ?>

                <section class="film-delete-panel">
                    <h2 class="film-delete-panel__title"><?= $isWishlist ? 'Retirer de la liste' : 'Supprimer de mes films' ?></h2>
                    <p class="hint">
                        <?= $isWishlist
                            ? 'Retire ce titre de vos envies.'
                            : 'Retire ce film de la dvdthèque, y compris tout son historique de visions.' ?>
                        Cette action est définitive.
                    </p>
                    <form method="post" action="/supprimer-film.php" class="inline-form"
                          onsubmit="return confirm('Supprimer définitivement « <?= Moncine\View::escape($film['titre']) ?> » de vos films ?\n\nL’historique des visions sera aussi effacé.');">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="film_id" value="<?= $filmId ?>">
                        <?php if (isset($filmListContext)): ?>
                            <?php require MONCINE_ROOT . '/templates/_film_list_context_fields.php'; ?>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-danger">Supprimer ce film</button>
                    </form>
                </section>

                <?php require MONCINE_ROOT . '/templates/_film_list_nav.php'; ?>

                <div class="result-actions">
                    <a href="/quiz.php" class="btn btn-primary">Chercher un film ce soir</a>
                    <a href="<?= Moncine\View::escape($listBackUrl ?? ($isWishlist ? '/souhaits.php' : '/films.php')) ?>"
                       class="btn btn-ghost">Retour à la liste</a>
                </div>
            </div>
        </article>
    <?php endif; ?>
</section>
