<?php
/** @var string $query */
/** @var list<array<string, mixed>> $films */
/** @var bool $searched */
/** @var list<string> $suggestions */
?>
<section class="personnes-page">
    <h1>Films par acteur ou réalisateur</h1>
    <p class="lead">
        Recherche dans tout le <strong>catalogue partagé</strong> Moncine (réalisateur et acteurs principaux,
        données TMDB). Pour chaque film, voyez s’il est déjà dans
        <strong>votre collection</strong>, dans <strong>vos envies</strong>, ou seulement au catalogue.
    </p>

    <form method="get" action="/personnes.php" class="personnes-search import-form">
        <label for="q">Nom de l’acteur ou du réalisateur</label>
        <input type="search" name="q" id="q" list="personnes-suggestions"
               value="<?= Moncine\View::escape($query) ?>"
               placeholder="ex. Harrison Ford, Denis Villeneuve…"
               autofocus required>
        <?php if ($suggestions !== []): ?>
            <datalist id="personnes-suggestions">
                <?php foreach ($suggestions as $name): ?>
                    <option value="<?= Moncine\View::escape($name) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Rechercher</button>
    </form>

    <?php if ($searched): ?>
        <?php if ($films === []): ?>
            <p class="alert alert-warning">
                Aucun film trouvé dans le catalogue pour « <?= Moncine\View::escape($query) ?> ».
                Vérifiez l’orthographe ou demandez l’ajout de l’œuvre au catalogue.
            </p>
        <?php else: ?>
            <p class="stats">
                <?= count($films) ?> film<?= count($films) > 1 ? 's' : '' ?> au catalogue
                pour « <strong><?= Moncine\View::escape($query) ?></strong> »
            </p>
            <p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
            <div class="table-scroll">
            <table class="films-table personnes-results">
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Année</th>
                        <th>Ma liste</th>
                        <th>Rôle</th>
                        <th>Réalisateur</th>
                        <th>Acteurs</th>
                        <th>Dernière vue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($films as $film):
                        $acteurs = Moncine\FilmManualEdit::acteursList($film);
                        $roles = $film['roles'] ?? [];
                        $presence = (string) ($film['library_presence'] ?? 'none');
                        $filmUrl = Moncine\View::personSearchFilmUrl($film);
                        ?>
                        <tr>
                            <td>
                                <a href="<?= Moncine\View::escape($filmUrl) ?>" class="film-link">
                                    <?= Moncine\View::escape($film['titre']) ?>
                                </a>
                            </td>
                            <td><?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '—' ?></td>
                            <td>
                                <?php
                                $presenceClass = match ($presence) {
                                    Moncine\LibraryStatut::COLLECTION => 'tag--presence-collection',
                                    Moncine\LibraryStatut::WISHLIST => 'tag--presence-wishlist',
                                    default => 'tag--presence-none',
                                };
                                ?>
                                <span class="tag <?= $presenceClass ?>">
                                    <?= Moncine\View::escape(Moncine\LibraryStatut::presenceLabel($presence)) ?>
                                </span>
                            </td>
                            <td>
                                <?php foreach ($roles as $role): ?>
                                    <span class="tag tag--role"><?= Moncine\View::escape($role) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><?= ($film['realisateur'] ?? '') !== '' ? Moncine\View::escape($film['realisateur']) : '—' ?></td>
                            <td><?= $acteurs !== [] ? Moncine\View::escape(implode(', ', $acteurs)) : '—' ?></td>
                            <td><?= !empty($film['derniere_vue'])
                                ? Moncine\View::escape(Moncine\HistoriqueRepository::formatDateVue((string) $film['derniere_vue']))
                                : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    <?php elseif ($suggestions === []): ?>
        <p class="hint">
            Aucun réalisateur ni acteur dans le catalogue pour l’instant.
            Les métadonnées TMDB remplissent ces champs lors de l’enrichissement des œuvres.
        </p>
    <?php else: ?>
        <p class="hint">Saisissez un nom puis validez. La liste déroulante propose les personnes déjà présentes dans le catalogue.</p>
    <?php endif; ?>
</section>
