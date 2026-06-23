<?php
/**
 * Tableau imprimable — jeux vidéo.
 *
 * @var list<array<string, mixed>> $games
 */
?>
<table class="print-table">
    <thead>
        <tr>
            <th class="col-poster">Jaquette</th>
            <th>Titre</th>
            <th class="col-narrow">Plateforme</th>
            <th class="col-narrow">Année</th>
            <th>Studio</th>
            <th>Genres</th>
            <th class="col-narrow">Support</th>
            <th class="col-narrow">Note</th>
            <th class="col-narrow">Ajouté le</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($games as $game):
            $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
            $displayTitle = (string) ($game['display_titre'] ?? $game['titre'] ?? '');
            $genreList = $game['genre_list'] ?? Moncine\GameGenre::parseList((string) ($game['genre'] ?? ''));
            $notePerso = $game['note_max'] ?? null;
            $noteLabel = Moncine\HistoriqueRepository::formatNoteSur10(
                $notePerso !== null && $notePerso !== '' ? (int) $notePerso : null
            );
            $supportText = Moncine\GameEditionIcons::supplementalText($game);
            if ($supportText === '' && !empty($game['is_digital'])) {
                $supportText = 'Dématérialisé';
            }
            ?>
            <tr>
                <td class="col-poster">
                    <?php if ($posterSrc !== ''): ?>
                        <img class="print-table__poster" src="<?= $posterSrc ?>" alt="" width="32" height="48" loading="lazy">
                    <?php endif; ?>
                </td>
                <td><?= Moncine\View::escape($displayTitle) ?></td>
                <td class="col-narrow"><?= Moncine\View::escape((string) ($game['platform_short'] ?? '')) ?></td>
                <td class="col-narrow"><?= (int) ($game['annee'] ?? 0) > 0 ? (int) $game['annee'] : '—' ?></td>
                <td><?= Moncine\View::escape((string) ($game['studio'] ?? '')) ?></td>
                <td><?= $genreList !== [] ? Moncine\View::escape(implode(', ', $genreList)) : '—' ?></td>
                <td class="col-narrow"><?= $supportText !== '' ? Moncine\View::escape($supportText) : '—' ?></td>
                <td class="col-narrow"><?= $noteLabel !== '' ? Moncine\View::escape($noteLabel) : '—' ?></td>
                <td class="col-narrow"><?= (string) ($game['added_at_label'] ?? '') !== ''
                    ? Moncine\View::escape((string) $game['added_at_label'])
                    : '—' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
