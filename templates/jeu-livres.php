<?php
/**
 * Liste des livres qui parlent d’un jeu.
 *
 * @var array<string, mixed>|null $game
 * @var string $gameTitle
 * @var list<array<string, mixed>> $books
 * @var string $backUrl
 */
$books = $books ?? [];
?>
<section class="collection-page game-detail-page">
    <?php if ($game === null): ?>
        <h1>Jeu introuvable</h1>
        <p class="hint">Ce jeu n’existe pas ou n’est plus dans le catalogue.</p>
        <p><a href="/jeux.php" class="btn btn-secondary">← Mes jeux</a></p>
    <?php else: ?>
        <header class="collection-page__header">
            <p>
                <a href="<?= Moncine\View::escape($backUrl) ?>" class="btn btn-secondary btn-sm">← <?= Moncine\View::escape($gameTitle) ?></a>
            </p>
            <h1>Livres</h1>
        </header>

        <?php if ($books === []): ?>
            <p class="hint">Aucun livre relié pour l’instant.</p>
        <?php else: ?>
            <p class="hint">
                <?= count($books) ?> livre<?= count($books) > 1 ? 's' : '' ?>
                qui parle<?= count($books) > 1 ? 'nt' : '' ?> de <?= Moncine\View::escape($gameTitle) ?>.
            </p>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Auteur</th>
                            <th>Année</th>
                            <th>Catégories</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <?php
                            $oeuvreId = (int) ($book['oeuvre_id'] ?? 0);
                            $titre = (string) ($book['display_titre'] ?? $book['titre'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <?php if ($oeuvreId > 0): ?>
                                        <a href="<?= Moncine\View::escape(Moncine\View::oeuvreLivreUrl($oeuvreId)) ?>">
                                            <?= Moncine\View::escape($titre) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= Moncine\View::escape($titre) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= Moncine\View::escape((string) ($book['auteur'] ?? '')) ?></td>
                                <td><?= (int) ($book['annee'] ?? 0) > 0 ? (int) $book['annee'] : '—' ?></td>
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
        <?php endif; ?>
    <?php endif; ?>
</section>
