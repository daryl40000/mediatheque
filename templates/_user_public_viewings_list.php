<?php
/**
 * Liste des visions d’un utilisateur (lecture seule) : dates et notes.
 *
 * @var list<array<string, mixed>> $viewings
 * @var int $targetUserId
 * @var string $sortBy
 * @var string $sortDir
 * @var int|null $yearFilter
 * @var string $profileDomain
 */
$yearFilter = isset($yearFilter) ? $yearFilter : null;
$profileDomain = $profileDomain ?? Moncine\MediaDomain::FILM;

$sortLink = static function (string $label, string $column) use (
    $targetUserId,
    $sortBy,
    $sortDir,
    $yearFilter,
    $profileDomain
): void {
    $active = $sortBy === $column;
    ?>
    <a href="<?= Moncine\View::escape(
        Moncine\View::userProfileListUrl($targetUserId, 'vus', $column, $sortBy, $sortDir, $yearFilter, $profileDomain)
    ) ?>"
       class="collection-grid-sort__link<?= $active ? ' is-active' : '' ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
?>
<?php if ($viewings === []): ?>
    <p class="hint">Aucune vision enregistrée<?= $yearFilter !== null ? ' pour cette année' : '' ?>.</p>
<?php else: ?>
    <p class="stats"><?= count($viewings) ?> vision<?= count($viewings) > 1 ? 's' : '' ?></p>
    <nav class="collection-grid-sort social-profile-list-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $sortLink('Date', 'date'); ?>
        <?php $sortLink('Titre', 'titre'); ?>
        <?php $sortLink('Note', 'note'); ?>
    </nav>
    <div class="table-scroll">
        <table class="films-table films-table--sortable social-viewings-table">
            <thead>
                <tr>
                    <th>Film</th>
                    <th>Date de vision</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($viewings as $row):
                    $posterSrc = Moncine\View::posterSrc($row['poster_url'] ?? null);
                    $titre = (string) ($row['titre'] ?? '');
                    $annee = (int) ($row['annee'] ?? 0);
                    $dateVue = Moncine\HistoriqueRepository::formatDateVue((string) ($row['date_vue'] ?? ''));
                    $note = $row['note'] ?? null;
                    ?>
                    <tr>
                        <td class="social-viewings-table__film">
                            <?php if ($posterSrc !== ''): ?>
                                <img class="social-viewings-table__poster" src="<?= $posterSrc ?>"
                                     alt="" width="40" height="60" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <span>
                                <strong><?= Moncine\View::escape($titre) ?></strong>
                                <?php if ($annee > 0): ?>
                                    <span class="hint"> (<?= $annee ?>)</span>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><?= Moncine\View::escape($dateVue) ?></td>
                        <td>
                            <?php if ($note !== null && $note !== ''): ?>
                                <span class="film-note"><?= (int) $note ?>/10</span>
                            <?php else: ?>
                                <span class="hint">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
