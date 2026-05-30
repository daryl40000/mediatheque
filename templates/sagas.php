<?php
/** @var string $saga */
/** @var bool $searched */
/** @var list<array<string, mixed>> $films */
/** @var list<array{saga: string, film_count: int}> $sagas */

$renameError = trim((string) ($_GET['rename_error'] ?? ''));
$renamed = isset($_GET['renamed']) && (string) $_GET['renamed'] === '1';
$renamedCount = isset($_GET['count']) ? (int) $_GET['count'] : 0;
?>
<section class="sagas-page">
    <h1><?= $searched ? 'Saga' : 'Sagas' ?></h1>

    <?php if ($searched): ?>
        <p class="breadcrumb">
            <a href="/sagas.php">Toutes les sagas</a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape($saga) ?></span>
        </p>

        <?php if ($renamed && $renamedCount > 0): ?>
            <p class="alert alert-success">
                Saga renommée : <?= $renamedCount ?> film<?= $renamedCount > 1 ? 's' : '' ?> mis à jour.
            </p>
        <?php endif; ?>
        <?php if ($renameError !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($renameError) ?></p>
        <?php endif; ?>

        <?php if ($films === []): ?>
            <p class="alert alert-warning">
                Aucun film trouvé pour cette saga.
                <a href="/sagas.php">Retour à la liste</a>.
            </p>
        <?php else: ?>
            <details class="sagas-rename-panel">
                <summary class="sagas-rename-panel__summary">Renommer cette saga</summary>
                <form method="post" action="/sagas.php" class="sagas-rename-form import-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="rename_saga">
                    <input type="hidden" name="saga_old" value="<?= Moncine\View::escape($saga) ?>">

                    <p class="hint">
                        Corrige le nom pour tous les films de la saga (ex. faute de frappe).
                    </p>

                    <label for="saga_new_name">Nouveau nom</label>
                    <input type="text" name="saga_new" id="saga_new_name" required
                           value="<?= Moncine\View::escape($saga) ?>"
                           autocomplete="off">

                    <button type="submit" class="btn btn-primary">Enregistrer le nouveau nom</button>
                </form>
            </details>

            <p class="stats">
                <?= count($films) ?> film<?= count($films) > 1 ? 's' : '' ?>
                dans « <?= Moncine\View::escape($saga) ?> »
            </p>
            <p class="hint">Ordre : numéro dans la saga, puis titre alphabétique.</p>

            <p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
            <div class="table-scroll">
            <table class="films-table sagas-detail">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Titre</th>
                        <th>Année</th>
                        <th>Réalisateur</th>
                        <th>Support</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($films as $film): ?>
                        <tr>
                            <td><?= Moncine\View::escape(
                                Moncine\FilmRepository::formatSagaOrdre((int) ($film['saga_ordre'] ?? 0))
                            ) ?></td>
                            <td>
                                <a href="/film.php?id=<?= (int) $film['id'] ?>" class="film-link">
                                    <?= Moncine\View::escape($film['titre']) ?>
                                </a>
                            </td>
                            <td><?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '—' ?></td>
                            <td><?= Moncine\View::escape($film['realisateur']) ?></td>
                            <td><?php
                                $supportKey = (string) ($film['support_physique'] ?? '');
                                require MONCINE_ROOT . '/templates/_support_link.php';
                                ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="lead">
            Regroupez vos suites de films (Jason Bourne, Harry Potter, etc.)
            et consultez-les dans l’ordre que vous avez défini.
        </p>
        <p class="hint">
            Sur <a href="/films.php">Mes films</a>, cochez plusieurs films puis utilisez
            <strong>Ajouter à une saga</strong>.
        </p>

        <?php if ($sagas === []): ?>
            <p class="alert alert-warning">
                Aucune saga pour l’instant. Sélectionnez des films parmi vos films
                pour les regrouper.
            </p>
        <?php else: ?>
            <ul class="sagas-list">
                <?php foreach ($sagas as $item): ?>
                    <li>
                        <a href="<?= Moncine\View::escape(Moncine\View::sagaUrl($item['saga'])) ?>"
                           class="sagas-list__link">
                            <?= Moncine\View::escape($item['saga']) ?>
                        </a>
                        <span class="sagas-list__count">
                            <?= (int) $item['film_count'] ?> film<?= (int) $item['film_count'] > 1 ? 's' : '' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>

    <p class="sagas-page__actions">
        <?php if ($searched): ?>
            <a href="/sagas.php" class="btn btn-secondary">Toutes les sagas</a>
        <?php endif; ?>
        <a href="/films.php" class="btn btn-secondary">Mes films</a>
    </p>
</section>
