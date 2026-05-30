<?php
/** @var bool $showChoice */
/** @var string $statut */
/** @var string $statutLabel */
/** @var list<string> $sagaSuggestions */
/** @var bool $usesCatalog */
/** @var string $saveError */
/** @var bool $hasTmdbKey */
/** @var bool $canManageCatalog */

$showChoice = $showChoice ?? true;
$usesCatalog = $usesCatalog ?? true;
$saveError = $saveError ?? '';
$sagaSuggestions = $sagaSuggestions ?? [];
$hasTmdbKey = $hasTmdbKey ?? false;
$canManageCatalog = $canManageCatalog ?? false;
$prefillOeuvreId = (int) ($prefillOeuvreId ?? 0);
$prefillFilm = $prefillFilm ?? null;
?>
<section class="add-film-page">
    <?php if ($showChoice): ?>
        <h1>Ajouter un film</h1>
        <?php if ($prefillOeuvreId > 0 && is_array($prefillFilm)): ?>
            <?php $posterSrc = Moncine\View::posterSrc($prefillFilm['poster_url'] ?? null); ?>
            <div class="add-film-choice-intro<?= $posterSrc !== '' ? ' add-film-choice-intro--with-poster' : '' ?>">
                <?php if ($posterSrc !== ''): ?>
                    <img class="film-poster add-film-choice-intro__poster" src="<?= $posterSrc ?>"
                         alt="Affiche de <?= Moncine\View::escape((string) ($prefillFilm['titre'] ?? '')) ?>"
                         width="200" height="300" decoding="async">
                <?php else: ?>
                    <div class="add-film-choice-intro__poster-placeholder" aria-hidden="true">
                        <span>Pas d’affiche</span>
                    </div>
                <?php endif; ?>
                <div class="add-film-choice-intro__text">
                    <p class="add-film-choice-intro__title">
                        <?= Moncine\View::escape((string) ($prefillFilm['titre'] ?? '')) ?>
                    </p>
                    <?php if (trim((string) ($prefillFilm['realisateur'] ?? '')) !== ''): ?>
                        <p class="add-film-choice-intro__meta">
                            <?= Moncine\View::escape((string) $prefillFilm['realisateur']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ((int) ($prefillFilm['annee'] ?? 0) > 0): ?>
                        <p class="add-film-choice-intro__meta"><?= (int) $prefillFilm['annee'] ?></p>
                    <?php endif; ?>
                    <p class="lead add-film-choice-intro__lead">
                        Choisissez une liste ci-dessous — le film y sera ajouté tout de suite.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <p class="lead">Où souhaitez-vous enregistrer ce titre ?</p>
        <?php endif; ?>

        <?php if ($saveError !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></p>
        <?php endif; ?>

        <div class="add-film-choice">
            <?php if ($prefillOeuvreId > 0 && is_array($prefillFilm)): ?>
                <form method="post" action="/ajouter-oeuvre-bibliotheque.php" class="add-film-choice__card add-film-choice__form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="oeuvre_id" value="<?= (int) $prefillOeuvreId ?>">
                    <input type="hidden" name="statut" value="<?= Moncine\View::escape(Moncine\LibraryStatut::COLLECTION) ?>">
                    <button type="submit" class="add-film-choice__submit">
                        <span class="add-film-choice__title">Mes films</span>
                        <span class="add-film-choice__hint">Ajouter à la collection partagée (DVD, Blu-ray…)</span>
                    </button>
                </form>
                <?php if ($usesCatalog): ?>
                    <form method="post" action="/ajouter-oeuvre-bibliotheque.php"
                          class="add-film-choice__card add-film-choice__card--wishlist add-film-choice__form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="oeuvre_id" value="<?= (int) $prefillOeuvreId ?>">
                        <input type="hidden" name="statut" value="<?= Moncine\View::escape(Moncine\LibraryStatut::WISHLIST) ?>">
                        <button type="submit" class="add-film-choice__submit">
                            <span class="add-film-choice__title"><?= Moncine\View::escape(Moncine\LibraryStatut::label(Moncine\LibraryStatut::WISHLIST)) ?></span>
                            <span class="add-film-choice__hint">Ajouter à votre liste d’envies personnelle</span>
                        </button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?= Moncine\View::escape(Moncine\View::addFilmUrl(Moncine\LibraryStatut::COLLECTION, $prefillOeuvreId)) ?>"
                   class="add-film-choice__card">
                    <span class="add-film-choice__title">Mes films</span>
                    <span class="add-film-choice__hint">Film que vous possédez déjà (DVD, Blu-ray…)</span>
                </a>
                <?php if ($usesCatalog): ?>
                    <a href="<?= Moncine\View::escape(Moncine\View::addFilmUrl(Moncine\LibraryStatut::WISHLIST, $prefillOeuvreId)) ?>"
                       class="add-film-choice__card add-film-choice__card--wishlist">
                        <span class="add-film-choice__title"><?= Moncine\View::escape(Moncine\LibraryStatut::label(Moncine\LibraryStatut::WISHLIST)) ?></span>
                        <span class="add-film-choice__hint">Film que vous aimeriez voir ou posséder un jour</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <p class="collection-page__footer-links">
            <a href="/">← Accueil</a>
        </p>
    <?php else: ?>
        <h1>Ajouter un film</h1>
        <p class="lead">
            Nouvelle entrée dans <strong><?= Moncine\View::escape($statutLabel) ?></strong>.
            Indiquez le titre et la catégorie (film, série, documentaire, spectacle).
            <?php if ($canManageCatalog): ?>
                Vous pouvez enregistrer avec enrichissement TMDB (affiche, synopsis, acteurs…)
                ou compléter la fiche plus tard depuis le catalogue.
            <?php elseif (Moncine\CatalogSubmission::canSubmit()): ?>
                Choisissez une œuvre déjà au catalogue, ou
                <a href="/proposer-oeuvre.php">proposez une nouvelle fiche</a> à l’administrateur.
            <?php else: ?>
                Choisissez une œuvre déjà présente au catalogue partagé.
            <?php endif; ?>
        </p>

        <?php if ($prefillOeuvreId > 0 && is_array($prefillFilm)): ?>
            <p class="alert alert-info">
                Cette œuvre est déjà dans le catalogue partagé : les champs ci-dessous sont préremplis.
                Enregistrez pour l’ajouter à votre bibliothèque sans créer de doublon.
            </p>
        <?php endif; ?>

        <?php if ($saveError !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></p>
        <?php endif; ?>

        <?php if ($canManageCatalog && !$hasTmdbKey): ?>
            <p class="alert alert-info">
                <a href="/import.php">Configurez une clé API TMDB</a> pour enrichir automatiquement vos fiches.
            </p>
        <?php endif; ?>

        <?php
        $prefillOeuvreId = (int) ($prefillOeuvreId ?? 0);
        $prefillFilm = $prefillFilm ?? null;
        $film = [
            'titre' => '',
            'titre_original' => '',
            'realisateur' => '',
            'acteur_1' => '',
            'acteur_2' => '',
            'acteur_3' => '',
            'annee' => 0,
            'nationalite' => '',
            'duree_min' => 0,
            'styles' => '',
            'saga' => '',
            'saga_ordre' => 0,
            'format_image' => '',
            'format_son' => '',
            'support_physique' => '',
            'poster_url' => '',
            'synopsis' => '',
            'tmdb_id' => 0,
            'tmdb_media_type' => '',
            'moncine_kind' => Moncine\MoncineContentKind::FILM,
            'saison_numero' => 0,
            'saison_label' => '',
            'ean' => '',
        ];
        if (is_array($prefillFilm)) {
            $film = array_merge($film, [
                'titre' => (string) ($prefillFilm['titre'] ?? ''),
                'titre_original' => (string) ($prefillFilm['titre_original'] ?? ''),
                'realisateur' => (string) ($prefillFilm['realisateur'] ?? ''),
                'acteur_1' => (string) ($prefillFilm['acteur_1'] ?? ''),
                'acteur_2' => (string) ($prefillFilm['acteur_2'] ?? ''),
                'acteur_3' => (string) ($prefillFilm['acteur_3'] ?? ''),
                'annee' => (int) ($prefillFilm['annee'] ?? 0),
                'nationalite' => (string) ($prefillFilm['nationalite'] ?? ''),
                'duree_min' => (int) ($prefillFilm['duree_min'] ?? 0),
                'styles' => (string) ($prefillFilm['styles'] ?? ''),
                'poster_url' => (string) ($prefillFilm['poster_url'] ?? ''),
                'synopsis' => (string) ($prefillFilm['synopsis'] ?? ''),
                'tmdb_id' => (int) ($prefillFilm['tmdb_id'] ?? 0),
                'tmdb_media_type' => (string) ($prefillFilm['tmdb_media_type'] ?? ''),
                'tmdb_tv_kind' => (string) ($prefillFilm['tmdb_tv_kind'] ?? ''),
                'moncine_kind' => (string) ($prefillFilm['moncine_kind'] ?? Moncine\MoncineContentKind::FILM),
            ]);
        }
        $formStatut = $statut;
        $cancelUrl = $statut === Moncine\LibraryStatut::WISHLIST ? '/souhaits.php' : '/films.php';
        if ($prefillOeuvreId > 0) {
            $cancelUrl = Moncine\View::oeuvreUrl($prefillOeuvreId);
        }
        require MONCINE_ROOT . '/templates/_film_add_form.php';
        ?>
    <?php endif; ?>
</section>
