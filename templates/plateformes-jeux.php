<?php
/**
 * @var string $message
 * @var string $error
 * @var list<array<string, mixed>> $platforms
 * @var array<string, int> $usageCounts
 * @var array<string, string> $kindChoices
 * @var array<string, string> $consoleStoreChoices
 */
?>
<section class="catalog-maintenance-page">
    <div class="catalog-admin-page__head">
        <div>
            <h1>Plateformes jeux</h1>
            <p class="lead">
                Gérez la liste des plateformes proposées à l’ajout et à la modification des jeux.
                Un même jeu catalogue peut couvrir plusieurs plateformes ; chaque utilisateur coche celles qu’il possède.
            </p>
            <p class="hint">
                <a href="/maintenance-catalogue.php">← Maintenance catalogue</a>
                · <a href="/catalogue.php">Catalogue</a>
            </p>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($message) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <section class="catalog-maintenance-panel">
        <h2>Ajouter une plateforme</h2>
        <form method="post" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="create">
            <label for="new_platform_key">Identifiant (sans espace)</label>
            <input type="text" name="platform_key" id="new_platform_key" maxlength="32" required
                   pattern="[a-z0-9_]+" placeholder="ex. ps3, amiga">
            <label for="new_label">Libellé affiché</label>
            <input type="text" name="label" id="new_label" maxlength="80" required placeholder="ex. PlayStation 3">
            <label for="new_short_label">Libellé court (listes)</label>
            <input type="text" name="short_label" id="new_short_label" maxlength="24" placeholder="ex. PS3">
            <label for="new_kind">Type</label>
            <select name="kind" id="new_kind">
                <?php foreach ($kindChoices as $key => $label): ?>
                    <option value="<?= Moncine\View::escape($key) ?>"><?= Moncine\View::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="new_console_store">Store console (si type Console)</label>
            <select name="console_store" id="new_console_store">
                <?php foreach ($consoleStoreChoices as $key => $label): ?>
                    <option value="<?= Moncine\View::escape($key) ?>"><?= Moncine\View::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="new_sort_order">Ordre d’affichage</label>
            <input type="number" name="sort_order" id="new_sort_order" min="0" max="9999" value="100">
            <button type="submit" class="btn btn-primary">Ajouter</button>
        </form>
    </section>

    <section class="catalog-maintenance-panel">
        <h2>Plateformes existantes</h2>
        <?php if ($platforms === []): ?>
            <p class="hint">Aucune plateforme.</p>
        <?php else: ?>
            <table class="catalog-maintenance-table">
                <thead>
                    <tr>
                        <th>Identifiant</th>
                        <th>Libellé</th>
                        <th>Type</th>
                        <th>Utilisations</th>
                        <th>Actif</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($platforms as $row):
                        $key = (string) ($row['platform_key'] ?? '');
                        $usage = (int) ($usageCounts[$key] ?? 0);
                        ?>
                        <tr>
                            <td><code><?= Moncine\View::escape($key) ?></code></td>
                            <td>
                                <form method="post" class="inline-form catalog-maintenance-inline-form">
                                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="platform_key" value="<?= Moncine\View::escape($key) ?>">
                                    <input type="text" name="label" value="<?= Moncine\View::escape((string) ($row['label'] ?? '')) ?>" maxlength="80" required>
                                    <input type="text" name="short_label" value="<?= Moncine\View::escape((string) ($row['short_label'] ?? '')) ?>" maxlength="24" placeholder="Court">
                                    <select name="kind">
                                        <?php foreach ($kindChoices as $kindKey => $kindLabel): ?>
                                            <option value="<?= Moncine\View::escape($kindKey) ?>"<?= ($row['kind'] ?? '') === $kindKey ? ' selected' : '' ?>>
                                                <?= Moncine\View::escape($kindLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="console_store">
                                        <?php foreach ($consoleStoreChoices as $storeKey => $storeLabel): ?>
                                            <option value="<?= Moncine\View::escape($storeKey) ?>"<?= ($row['console_store'] ?? '') === $storeKey ? ' selected' : '' ?>>
                                                <?= Moncine\View::escape($storeLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>" min="0" max="9999" style="width:5rem">
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="active" value="1"<?= !empty($row['active']) ? ' checked' : '' ?>>
                                        Active
                                    </label>
                                    <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                </form>
                            </td>
                            <td><?= Moncine\View::escape((string) ($kindChoices[(string) ($row['kind'] ?? '')] ?? $row['kind'] ?? '')) ?></td>
                            <td><?= $usage ?></td>
                            <td><?= !empty($row['active']) ? 'Oui' : 'Non' ?></td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</section>
