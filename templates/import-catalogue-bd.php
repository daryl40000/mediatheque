<?php
/**
 * @var bool $bdModuleAvailable
 * @var string $message
 * @var list<string> $errors
 * @var array<string, mixed>|null $importResult
 */
?>
<section class="catalog-admin-page">
    <div class="catalog-admin-page__head">
        <div>
            <h1>Import catalogue — BD / Manga</h1>
            <p class="lead">
                Alimente le <strong>catalogue partagé</strong> (séries + tomes) à partir d’un fichier
                <strong>CSV</strong> (séparateur <code>;</code>).
                Voir la documentation : <code>doc/import-bd.md</code>.
            </p>
        </div>
        <p class="catalog-admin-page__badge hint">Réservé à l’administrateur catalogue</p>
    </div>

    <?php if ($message !== ''): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($message) ?></p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endforeach; ?>

    <?php if ($importResult !== null): ?>
        <section class="catalog-maintenance-panel">
            <h2><?= !empty($importResult['dry_run']) ? 'Simulation (aucune écriture)' : 'Résultat de l’import' ?></h2>
            <ul class="catalog-maintenance-stats__grid">
                <li class="catalog-maintenance-stat">
                    <span class="catalog-maintenance-stat__value"><?= (int) ($importResult['series_created'] ?? 0) ?></span>
                    <span class="catalog-maintenance-stat__label">Séries créées</span>
                </li>
                <li class="catalog-maintenance-stat">
                    <span class="catalog-maintenance-stat__value"><?= (int) ($importResult['series_reused'] ?? 0) ?></span>
                    <span class="catalog-maintenance-stat__label">Séries déjà présentes</span>
                </li>
                <li class="catalog-maintenance-stat">
                    <span class="catalog-maintenance-stat__value"><?= (int) ($importResult['tomes_created'] ?? 0) ?></span>
                    <span class="catalog-maintenance-stat__label"><?= !empty($importResult['dry_run']) ? 'Tomes à créer' : 'Tomes créés' ?></span>
                </li>
                <li class="catalog-maintenance-stat">
                    <span class="catalog-maintenance-stat__value"><?= (int) ($importResult['tomes_skipped'] ?? 0) ?></span>
                    <span class="catalog-maintenance-stat__label">Tomes ignorés</span>
                </li>
                <li class="catalog-maintenance-stat">
                    <span class="catalog-maintenance-stat__value"><?= (int) ($importResult['library_attached'] ?? 0) ?></span>
                    <span class="catalog-maintenance-stat__label">Ajouts collection</span>
                </li>
            </ul>
            <?php if (!empty($importResult['errors'])): ?>
                <h3>Messages</h3>
                <ul>
                    <?php foreach ($importResult['errors'] as $err): ?>
                        <li><?= Moncine\View::escape((string) $err) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!$bdModuleAvailable): ?>
        <p class="alert alert-warning">Le module BD n’est pas disponible (migrations manquantes ?).</p>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="catalog-maintenance-panel">
            <?= Moncine\View::csrfField() ?>
            <h2>Fichier CSV</h2>
            <p class="hint">
                Colonnes minimales : <code>serie;tome_numero</code>.
                Exemple : <code>Astérix;bd;1;…</code> — détails dans <code>doc/import-bd.md</code>.
            </p>
            <p>
                <label for="csv_file">Fichier</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="dry_run" value="1" checked>
                    Essai à blanc (ne rien écrire en base)
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="skip_existing" value="1" checked>
                    Ignorer les tomes déjà présents (même série + numéro + hors-série)
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="add_to_library" value="1">
                    Ajouter aussi à ma collection
                </label>
            </p>
            <p>
                <button type="submit" class="btn">Importer</button>
                <a class="btn btn-secondary" href="/bd.php">Mes BD</a>
            </p>
        </form>
    <?php endif; ?>
</section>
