<?php
/**
 * Sagas livres — liste et détail.
 *
 * @var string $saga
 * @var bool $searched
 * @var list<array<string, mixed>> $books
 * @var list<array{saga: string, book_count: int}> $sagas
 * @var string $moduleError
 */

$renameError = trim((string) ($_GET['rename_error'] ?? ''));
$renamed = isset($_GET['renamed']) && (string) $_GET['renamed'] === '1';
$renamedCount = isset($_GET['count']) ? (int) $_GET['count'] : 0;
$moduleError = $moduleError ?? '';
?>
<section class="sagas-page">
    <h1><?= $searched ? 'Saga' : 'Sagas livres' ?></h1>

    <?php if ($moduleError !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></p>
    <?php elseif ($searched): ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape(Moncine\View::sagasLivresUrl()) ?>">Toutes les sagas</a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape($saga) ?></span>
        </p>

        <?php if ($renamed && $renamedCount > 0): ?>
            <p class="alert alert-success">
                Saga renommée : <?= $renamedCount ?> livre<?= $renamedCount > 1 ? 's' : '' ?> mis à jour.
            </p>
        <?php endif; ?>
        <?php if ($renameError !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($renameError) ?></p>
        <?php endif; ?>

        <?php if ($books === []): ?>
            <p class="alert alert-warning">
                Aucun livre trouvé pour cette saga.
                <a href="<?= Moncine\View::escape(Moncine\View::sagasLivresUrl()) ?>">Retour à la liste</a>.
            </p>
        <?php else: ?>
            <details class="sagas-rename-panel">
                <summary class="sagas-rename-panel__summary">Renommer cette saga</summary>
                <form method="post" action="/sagas-livres.php" class="sagas-rename-form import-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="rename_saga">
                    <input type="hidden" name="saga_old" value="<?= Moncine\View::escape($saga) ?>">

                    <p class="hint">
                        Corrige le nom pour tous les livres de la saga (ex. faute de frappe).
                    </p>

                    <label for="saga_new_name">Nouveau nom</label>
                    <input type="text" name="saga_new" id="saga_new_name" required
                           value="<?= Moncine\View::escape($saga) ?>"
                           autocomplete="off">

                    <button type="submit" class="btn btn-primary">Enregistrer le nouveau nom</button>
                </form>
            </details>

            <p class="stats">
                <?= count($books) ?> livre<?= count($books) > 1 ? 's' : '' ?>
                dans « <?= Moncine\View::escape($saga) ?> »
            </p>
            <p class="hint">
                Ordre : numéro dans la saga, puis titre alphabétique.
                Cliquez sur une vignette pour ouvrir la fiche.
            </p>

            <?php require MONCINE_ROOT . '/templates/_saga_livres_grid.php'; ?>
        <?php endif; ?>
    <?php else: ?>
        <p class="lead">
            Les sagas regroupent plusieurs livres d’une même histoire
            (ex. Harry Potter, Le Seigneur des Anneaux).
        </p>
        <p><a href="/livres.php" class="btn btn-secondary btn-sm">← Mes livres</a></p>

        <?php if ($sagas === []): ?>
            <p class="hint">Aucune saga pour l’instant. Indiquez une saga en ajoutant ou en modifiant un livre.</p>
        <?php else: ?>
            <ul class="sagas-list" role="list">
                <?php foreach ($sagas as $row): ?>
                    <?php
                    $name = (string) ($row['saga'] ?? '');
                    $count = (int) ($row['book_count'] ?? 0);
                    if ($name === '') {
                        continue;
                    }
                    ?>
                    <li role="listitem">
                        <a href="<?= Moncine\View::escape(Moncine\View::sagasLivresUrl($name)) ?>">
                            <?= Moncine\View::escape($name) ?>
                        </a>
                        <span class="hint">(<?= $count ?> livre<?= $count > 1 ? 's' : '' ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
