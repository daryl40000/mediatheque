<?php
/**
 * Mes livres — vue liste (tableau).
 *
 * @var list<array<string, mixed>> $books
 * @var callable $sortHeader
 */
?>
<p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
<div class="table-wrap table-scroll">
    <table class="films-table films-table--sortable data-table">
        <thead>
            <tr>
                <th class="col-poster" scope="col">Couverture</th>
                <?php $sortHeader('Titre', 'titre'); ?>
                <?php $sortHeader('Auteur', 'auteur'); ?>
                <?php $sortHeader('Année', 'annee'); ?>
                <?php $sortHeader('Éditeur', 'editeur'); ?>
                <th>Catégories</th>
            </tr>
        </thead>
        <tbody data-magazine-series-grid>
            <?php foreach ($books as $book): ?>
                <?php
                $bibId = (int) ($book['bib_id'] ?? 0);
                $categoryKeys = Moncine\LivreCategory::filterKeysForBook($book);
                $livreUrl = Moncine\View::livreUrl($bibId);
                $posterSrc = Moncine\View::posterSrc($book['poster_url'] ?? null);
                $displayTitle = (string) ($book['display_titre'] ?? $book['titre'] ?? '');
                ?>
                <tr data-series-categories="<?= Moncine\View::escape(implode(',', $categoryKeys)) ?>">
                    <td class="col-poster">
                        <a href="<?= Moncine\View::escape($livreUrl) ?>" class="films-table__poster-link"
                           title="Voir la fiche : <?= Moncine\View::escape($displayTitle) ?>">
                            <?php if ($posterSrc !== ''): ?>
                                <img class="films-table__poster" src="<?= $posterSrc ?>"
                                     alt="Couverture de <?= Moncine\View::escape($displayTitle) ?>"
                                     width="44" height="66" loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="films-table__poster films-table__poster--empty" aria-hidden="true"></span>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td>
                        <a href="<?= Moncine\View::escape($livreUrl) ?>">
                            <?= Moncine\View::escape($displayTitle) ?>
                        </a>
                        <?php if (trim((string) ($book['sous_titre'] ?? '')) !== ''): ?>
                            <div class="hint"><?= Moncine\View::escape((string) $book['sous_titre']) ?></div>
                        <?php endif; ?>
                        <?php if (trim((string) ($book['saga'] ?? '')) !== ''): ?>
                            <div class="hint">
                                Saga :
                                <a href="<?= Moncine\View::escape(Moncine\View::sagasLivresUrl((string) $book['saga'])) ?>">
                                    <?= Moncine\View::escape((string) $book['saga']) ?>
                                </a>
                                <?php if ((int) ($book['saga_ordre'] ?? 0) > 0): ?>
                                    (n°<?= (int) $book['saga_ordre'] ?>)
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= Moncine\View::escape((string) ($book['auteur'] ?? '')) ?></td>
                    <td><?= (int) ($book['annee'] ?? 0) > 0 ? (int) $book['annee'] : '—' ?></td>
                    <td><?= Moncine\View::escape((string) ($book['editeur'] ?? '')) ?></td>
                    <td>
                        <?php
                        $labelPrefix = '';
                        require MONCINE_ROOT . '/templates/_livre_categories_display.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
