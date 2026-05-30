<?php
/**
 * Tableau imprimable — collection (Mes films).
 *
 * @var list<array<string, mixed>> $films
 */
?>
<table class="print-table">
    <thead>
        <tr>
            <th class="col-poster">Aff.</th>
            <th>Titre</th>
            <th class="col-narrow">Type</th>
            <th class="col-narrow">Année</th>
            <th>Réalisateur</th>
            <th class="col-narrow">Durée</th>
            <th>Style</th>
            <th>Saga</th>
            <th class="col-narrow">Support</th>
            <th class="col-narrow">Note</th>
            <th class="col-narrow">Vue</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($films as $film):
            $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null);
            $sagaLabel = trim((string) ($film['saga'] ?? ''));
            $sagaOrdre = (int) ($film['saga_ordre'] ?? 0);
            $sagaText = $sagaLabel !== ''
                ? ($sagaOrdre > 0 ? $sagaLabel . ' #' . $sagaOrdre : $sagaLabel)
                : '—';
            $notePerso = $film['note_max'] ?? null;
            $noteLabel = Moncine\HistoriqueRepository::formatNoteSur10(
                $notePerso !== null && $notePerso !== '' ? (int) $notePerso : null
            );
            ?>
            <tr>
                <td class="col-poster">
                    <?php if ($posterSrc !== ''): ?>
                        <img class="print-table__poster" src="<?= $posterSrc ?>" alt="" width="32" height="48" loading="lazy">
                    <?php endif; ?>
                </td>
                <td><?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?></td>
                <td class="col-narrow"><?= Moncine\View::escape(Moncine\ContentKindFilter::listLabel($film)) ?></td>
                <td class="col-narrow"><?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '—' ?></td>
                <td><?= Moncine\View::escape((string) ($film['realisateur'] ?? '')) ?></td>
                <td class="col-narrow"><?= (int) ($film['duree_min'] ?? 0) > 0 ? (int) $film['duree_min'] . ' min' : '—' ?></td>
                <td><?= Moncine\View::escape((string) ($film['styles'] ?? '')) ?></td>
                <td><?= Moncine\View::escape($sagaText) ?></td>
                <td class="col-narrow"><?= Moncine\View::escape(Moncine\SupportPhysique::label((string) ($film['support_physique'] ?? ''))) ?: '—' ?></td>
                <td class="col-narrow"><?= $noteLabel !== '' ? Moncine\View::escape($noteLabel) : '—' ?></td>
                <td class="col-narrow"><?= !empty($film['derniere_vue'])
                    ? Moncine\View::escape(Moncine\HistoriqueRepository::formatDateVue((string) $film['derniere_vue']))
                    : '—' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
