<?php
/**
 * Mes films — vue liste (tableau).
 *
 * @var list<array<string, mixed>> $films
 * @var callable $sortHeader
 * @var Moncine\FilmListContext $filmListContext
 */
$filmListContext = $filmListContext ?? Moncine\FilmListContext::forCollection($sortBy ?? 'titre', $sortDir ?? 'asc', $query ?? '', $kindFilter ?? '');
?>
<p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
<div class="table-scroll">
<table class="films-table films-table--sortable films-table--selectable films-table--wide">
    <thead>
        <tr>
            <th class="col-select" scope="col">
                <label class="collection-select-all" title="Tout sélectionner sur cette page">
                    <input type="checkbox" id="collection-select-all" aria-label="Tout sélectionner">
                </label>
            </th>
            <th class="col-poster" scope="col">Affiche</th>
            <?php $sortHeader('Titre', 'titre'); ?>
            <th>Type</th>
            <?php $sortHeader('Année', 'annee'); ?>
            <th>Nationalité</th>
            <?php $sortHeader('Réalisateur', 'realisateur'); ?>
            <?php $sortHeader('Durée', 'duree_min'); ?>
            <?php $sortHeader('Style', 'styles'); ?>
            <th>Saga</th>
            <?php $sortHeader('Support', 'support_physique'); ?>
            <?php $sortHeader('Notes', 'note'); ?>
            <?php $sortHeader('Dernière vue', 'derniere_vue'); ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($films as $film):
            $sagaLabel = trim((string) ($film['saga'] ?? ''));
            $filmId = (int) $film['id'];
            $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null);
            $filmUrl = $filmListContext->filmUrl($filmId);
            ?>
            <tr>
                <td class="col-select">
                    <input type="checkbox" name="film_ids[]"
                           value="<?= $filmId ?>"
                           class="collection-film-cb"
                           aria-label="Sélectionner <?= Moncine\View::escape($film['titre']) ?>">
                </td>
                <td class="col-poster">
                    <a href="<?= Moncine\View::escape($filmUrl) ?>" class="films-table__poster-link"
                       title="Voir la fiche : <?= Moncine\View::escape($film['titre']) ?>">
                        <?php if ($posterSrc !== ''): ?>
                            <img class="films-table__poster" src="<?= $posterSrc ?>"
                                 alt="Affiche de <?= Moncine\View::escape($film['titre']) ?>"
                                 width="44" height="66" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="films-table__poster films-table__poster--empty"
                                  aria-hidden="true"></span>
                        <?php endif; ?>
                    </a>
                </td>
                <td>
                    <a href="<?= Moncine\View::escape($filmUrl) ?>" class="film-link">
                        <?= Moncine\View::escape($film['titre']) ?>
                    </a>
                </td>
                <td>
                    <span class="tag tag--kind tag--kind-<?= Moncine\View::escape(\Moncine\ContentKindFilter::categoryKey($film)) ?>">
                        <?= Moncine\View::escape(\Moncine\ContentKindFilter::listLabel($film)) ?>
                    </span>
                </td>
                <td><?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '—' ?></td>
                <td><?= Moncine\View::escape(
                    Moncine\FilmRepository::formatNationalite((string) ($film['nationalite'] ?? ''))
                ) ?></td>
                <td><?= Moncine\View::escape($film['realisateur']) ?></td>
                <td><?= (int) $film['duree_min'] > 0 ? (int) $film['duree_min'] . ' min' : '—' ?></td>
                <td><?= Moncine\View::escape($film['styles']) ?></td>
                <td>
                    <?php if ($sagaLabel !== ''): ?>
                        <?php
                        $sagaName = $sagaLabel;
                        $sagaOrdre = (int) ($film['saga_ordre'] ?? 0);
                        require MONCINE_ROOT . '/templates/_saga_link.php';
                        ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?php $supportKey = (string) ($film['support_physique'] ?? ''); require MONCINE_ROOT . '/templates/_support_link.php'; ?></td>
                <td><?php $showFoyerAverage = true; $layout = 'stacked'; require MONCINE_ROOT . '/templates/_film_ratings.php'; ?></td>
                <td><?= $film['derniere_vue']
                    ? Moncine\View::escape(Moncine\HistoriqueRepository::formatDateVue((string) $film['derniere_vue']))
                    : '—' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
