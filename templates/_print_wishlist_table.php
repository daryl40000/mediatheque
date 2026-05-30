<?php
/**
 * Tableau imprimable — envies personnelles.
 *
 * @var list<array<string, mixed>> $films
 * @var array<int, list<array<string, mixed>>> $wishlistTargetsByFilmId
 */
$wishlistTargetsByFilmId = $wishlistTargetsByFilmId ?? [];
?>
<table class="print-table">
    <thead>
        <tr>
            <th>Titre</th>
            <th class="col-narrow">Année</th>
            <th>Nationalité</th>
            <th>Réalisateur</th>
            <th>Style</th>
            <th>Versions recherchées</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($films as $film):
            $filmId = (int) ($film['id'] ?? 0);
            $targets = $wishlistTargetsByFilmId[$filmId] ?? [];
            ?>
            <tr>
                <td><?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?></td>
                <td class="col-narrow"><?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '—' ?></td>
                <td><?= Moncine\View::escape(
                    Moncine\FilmRepository::formatNationalite((string) ($film['nationalite'] ?? ''))
                ) ?: '—' ?></td>
                <td><?= Moncine\View::escape((string) ($film['realisateur'] ?? '')) ?></td>
                <td><?= Moncine\View::escape((string) ($film['styles'] ?? '')) ?></td>
                <td><?= $targets !== []
                    ? Moncine\View::escape(Moncine\View::formatWishlistTargetsSummary($targets))
                    : '—' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
