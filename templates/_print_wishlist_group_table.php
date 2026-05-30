<?php
/**
 * Tableau imprimable — envies agrégées du groupe.
 *
 * @var list<array<string, mixed>> $films
 */
?>
<table class="print-table">
    <thead>
        <tr>
            <th class="col-narrow">Demandes</th>
            <th>Titre</th>
            <th class="col-narrow">Année</th>
            <th>Personnes</th>
            <th>Réalisateur</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($films as $film):
            $voters = is_array($film['voters'] ?? null) ? $film['voters'] : [];
            ?>
            <tr>
                <td class="col-narrow"><?= (int) ($film['vote_count'] ?? 0) ?></td>
                <td><?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?></td>
                <td class="col-narrow"><?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '—' ?></td>
                <td><?= Moncine\View::escape(Moncine\View::formatVoterNames($voters)) ?></td>
                <td><?= Moncine\View::escape((string) ($film['realisateur'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
