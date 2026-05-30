<?php
/** @var string $type */
/** @var string $typeLabel */
/** @var list<array<string, mixed>> $films */
/** @var bool $searched */
/** @var list<string> $availableTypes */
?>
<section class="support-page">
    <h1>Films par support physique</h1>
    <p class="lead">
        Retrouvez vos films selon le disque :
        <strong>DVD</strong>, <strong>Blu-ray</strong> ou <strong>Blu-ray 4K</strong>.
    </p>

    <nav class="ui-pill-nav support-filters" aria-label="Types de support">
        <?php foreach (Moncine\SupportPhysique::choices() as $key => $label): ?>
            <?php $active = $searched && $type === $key ? ' ui-pill--active' : ''; ?>
            <a href="<?= Moncine\View::escape(Moncine\View::supportFilterUrl($key)) ?>"
               class="ui-pill<?= $active ?>"><?= Moncine\View::escape($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($searched): ?>
        <?php if ($films === []): ?>
            <p class="alert alert-warning">
                Aucun film enregistré avec le support « <?= Moncine\View::escape($typeLabel) ?> ».
                Indiquez le support sur la fiche de chaque film (section Support &amp; texte).
            </p>
        <?php else: ?>
            <p class="stats">
                <?= count($films) ?> film<?= count($films) > 1 ? 's' : '' ?>
                en <?= Moncine\View::escape($typeLabel) ?>
            </p>
            <p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
            <div class="table-scroll">
            <table class="films-table personnes-results">
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Année</th>
                        <th>Réalisateur</th>
                        <th>Style</th>
                        <th>Dernière vue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($films as $film): ?>
                        <tr>
                            <td>
                                <a href="/film.php?id=<?= (int) $film['id'] ?>" class="film-link">
                                    <?= Moncine\View::escape($film['titre']) ?>
                                </a>
                            </td>
                            <td><?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '—' ?></td>
                            <td><?= Moncine\View::escape($film['realisateur']) ?></td>
                            <td><?= Moncine\View::escape($film['styles']) ?></td>
                            <td><?= !empty($film['derniere_vue'])
                                ? Moncine\View::escape(Moncine\HistoriqueRepository::formatDateVue((string) $film['derniere_vue']))
                                : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="hint">Choisissez un type de support ci-dessus.</p>
        <?php if ($availableTypes !== []): ?>
            <p class="hint">Supports renseignés parmi vos films :
                <?php foreach ($availableTypes as $i => $key): ?>
                    <?php if ($i > 0): ?>, <?php endif; ?>
                    <a href="<?= Moncine\View::escape(Moncine\View::supportFilterUrl($key)) ?>"><?= Moncine\View::escape(Moncine\SupportPhysique::label($key)) ?></a>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <p><a href="/films.php" class="btn btn-secondary">Retour à mes films</a></p>
</section>
