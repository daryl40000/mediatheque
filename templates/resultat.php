<section>
    <h1>Votre proposition</h1>

    <?php if (isset($_GET['vu'])): ?>
        <p class="alert alert-success">
            Vision enregistrée<?= !empty($_GET['vu_date'])
                ? ' le ' . Moncine\View::escape((string) $_GET['vu_date'])
                : '' ?>.
        </p>
    <?php endif; ?>
    <?php if (!empty($_GET['vu_error'])): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['vu_error']) ?></p>
    <?php endif; ?>

    <?php if ($pick === null): ?>
        <div class="alert alert-warning">
            <?php if ($noMoreFilms ?? false): ?>
                <p>Tous les films correspondant à vos critères ont déjà été proposés dans cette session.</p>
            <?php else: ?>
                <p>Aucun film ne correspond à vos critères. Essayez d'assouplir le questionnaire (type, durée, styles, « déjà vus »).</p>
            <?php endif; ?>
            <div class="result-actions">
                <?php if ($hasSession ?? false): ?>
                    <form method="post" action="/resultat.php" class="inline-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="action" value="retirage">
                        <button type="submit" class="btn btn-secondary">Réessayer un tirage</button>
                    </form>
                <?php endif; ?>
                <?php if ($hasRatings ?? false): ?>
                    <a href="/meilleurs.php" class="btn btn-accent">Voir les mieux notés</a>
                <?php endif; ?>
                <a href="/quiz.php?reset=1" class="btn btn-primary">Refaire la sélection</a>
            </div>
        </div>
    <?php else:
        $film = $pick['film'];
        $filmId = (int) $film['id'];
        $returnPage = 'resultat';
        ?>
        <div class="result-proposal-toolbar">
            <?php require MONCINE_ROOT . '/templates/_notes_form.php'; ?>
            <div class="result-proposal-toolbar__actions">
                <form method="post" action="/resultat.php" class="inline-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="retirage">
                    <input type="hidden" name="exclude_film_id" value="<?= $filmId ?>">
                    <button type="submit" class="btn btn-secondary">Autre tirage</button>
                </form>
                <?php if ($hasRatings ?? false): ?>
                    <a href="/meilleurs.php" class="btn btn-accent">Voir les mieux notés</a>
                <?php endif; ?>
            </div>
        </div>

        <?php $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null); ?>
        <article class="film-card film-card--featured<?= $posterSrc !== '' ? ' film-card--with-poster' : '' ?>">
            <?php if ($posterSrc !== ''): ?>
                <img class="film-poster" src="<?= $posterSrc ?>"
                     alt="Affiche de <?= Moncine\View::escape($film['titre']) ?>" loading="lazy">
            <?php endif; ?>
            <div class="film-card__body">
            <h2><?= Moncine\View::escape($film['titre']) ?></h2>
            <?php if (!empty($film['realisateur'])): ?>
                <p class="meta">Réalisateur : <?= Moncine\View::escape($film['realisateur']) ?></p>
            <?php endif; ?>
            <?php
            $acteurs = Moncine\FilmManualEdit::acteursList($film);
            if ($acteurs !== []):
                ?>
                <p class="meta">Avec : <?= Moncine\View::escape(implode(', ', $acteurs)) ?></p>
            <?php endif; ?>
            <ul class="film-details">
                <?php if ((int) ($film['annee'] ?? 0) > 0): ?>
                    <li>Année : <?= (int) $film['annee'] ?></li>
                <?php endif; ?>
                <?php if ((int) $film['duree_min'] > 0): ?>
                    <li>Durée : <?= (int) $film['duree_min'] ?> min</li>
                <?php endif; ?>
                <?php if (!empty($film['styles'])): ?>
                    <li>Style : <?= Moncine\View::escape($film['styles']) ?></li>
                <?php endif; ?>
                <?php if (!empty($film['format_image'])): ?>
                    <li>Image : <?= Moncine\View::escape($film['format_image']) ?></li>
                <?php endif; ?>
                <?php if (!empty($film['format_son'])): ?>
                    <li>Bande sonore : <?= Moncine\View::escape($film['format_son']) ?></li>
                <?php endif; ?>
            </ul>

            <?php if (!empty($film['synopsis'])): ?>
                <p class="film-synopsis"><?= Moncine\View::escape($film['synopsis']) ?></p>
            <?php endif; ?>

            <?php if (!empty($showTmdbEnrich)): ?>
                <?php require MONCINE_ROOT . '/templates/_enrich_film_panel.php'; ?>
            <?php endif; ?>

            <?php
            $return = 'resultat';
            $submitLabel = 'On le regarde ce soir';
            $compact = true;
            require MONCINE_ROOT . '/templates/_marquer_vu_form.php';
            ?>
            </div>
        </article>

        <div class="result-actions">
            <a href="/quiz.php?reset=1" class="btn btn-primary">Refaire la sélection</a>
            <a href="/" class="btn btn-ghost">Accueil</a>
        </div>

        <?php if (!empty($alternatives)): ?>
            <h3>Autres pistes</h3>
            <ul class="film-list">
                <?php foreach ($alternatives as $alt): ?>
                    <li>
                        <strong><?= Moncine\View::escape($alt['film']['titre']) ?></strong>
                        <?php if (!empty($alt['film']['styles'])): ?>
                            <span class="tag"><?= Moncine\View::escape($alt['film']['styles']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
