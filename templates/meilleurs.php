<section>
    <h1>Vos mieux choix</h1>
    <p class="lead">Films notés pendant cette session, du plus au moins apprécié.</p>

    <?php if ($entries === []): ?>
        <p class="alert alert-info">Aucune note enregistrée pour l'instant.</p>
        <a href="/quiz.php" class="btn btn-primary">Lancer le questionnaire</a>
    <?php else: ?>
        <ul class="rated-list">
            <?php foreach ($entries as $entry):
                $film = $entry['film'];
                $topClass = $entry['is_top'] ? ' rated-item--top' : '';
                ?>
                <li class="rated-item<?= $topClass ?>">
                    <div class="rated-item__head">
                        <span class="rating-badge rating-badge--<?= Moncine\View::escape($entry['note_key']) ?>">
                            <?= Moncine\View::escape($entry['label']) ?>
                        </span>
                        <?php if ($entry['is_top']): ?>
                            <span class="tag tag--top">Meilleure note</span>
                        <?php endif; ?>
                    </div>
                    <h2><?= Moncine\View::escape($film['titre']) ?></h2>
                    <?php if (!empty($film['realisateur'])): ?>
                        <p class="meta"><?= Moncine\View::escape($film['realisateur']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($film['styles'])): ?>
                        <p class="meta"><?= Moncine\View::escape($film['styles']) ?></p>
                    <?php endif; ?>

                    <?php
                    $filmId = (int) $film['id'];
                    $currentRating = $entry['note_key'];
                    $returnPage = 'meilleurs';
                    require MONCINE_ROOT . '/templates/_notes_form.php';
                    ?>

                    <a href="/resultat.php?film_id=<?= $filmId ?>" class="btn btn-secondary btn-sm">Voir la fiche</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="result-actions">
        <a href="/resultat.php?tirage=1" class="btn btn-primary">Retour au tirage</a>
        <a href="/quiz.php?reset=1" class="btn btn-secondary">Refaire la sélection</a>
        <a href="/" class="btn btn-ghost">Accueil</a>
    </div>
</section>
