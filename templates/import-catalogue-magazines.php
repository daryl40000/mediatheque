<?php
/**
 * @var bool $magazineModuleAvailable
 * @var string $message
 * @var list<string> $errors
 * @var array<string, mixed>|null $importResult
 * @var string $defaultJsonPath
 */
?>
<section class="catalog-admin-page">
    <div class="catalog-admin-page__head">
        <div>
            <h1>Import catalogue — magazines</h1>
            <p class="lead">
                Alimente le <strong>catalogue partagé</strong> (séries + numéros) à partir d’un export JSON,
                par exemple celui produit par <code>php lib/cli/abm-fetch-catalog.php</code>.
                Aucune entrée n’est ajoutée à votre bibliothèque personnelle.
            </p>
        </div>
        <p class="catalog-admin-page__badge hint">
            Réservé à l’administrateur —
            <a href="/export-catalogue-magazines.php">Exporter le catalogue JSON</a>
        </p>
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
                    <span class="catalog-maintenance-stat__value"><?= (int) ($importResult['issues_created'] ?? 0) ?></span>
                    <span class="catalog-maintenance-stat__label"><?= !empty($importResult['dry_run']) ? 'Numéros à créer' : 'Numéros créés' ?></span>
                </li>
                <li class="catalog-maintenance-stat">
                    <span class="catalog-maintenance-stat__value"><?= (int) ($importResult['issues_skipped'] ?? 0) ?></span>
                    <span class="catalog-maintenance-stat__label">Numéros ignorés (doublons)</span>
                </li>
            </ul>
            <?php if (
                (int) ($importResult['issue_covers_cached'] ?? 0) > 0
                || (int) ($importResult['issue_covers_remaining'] ?? 0) > 0
                || !empty($importResult['cover_batch_reached'])
            ): ?>
                <p class="hint">
                    Logos séries mis en cache : <?= (int) ($importResult['series_logos_cached'] ?? 0) ?> —
                    couvertures numéros : <?= (int) ($importResult['issue_covers_cached'] ?? 0) ?>.
                    <?php if ((int) ($importResult['issue_covers_failed'] ?? 0) > 0): ?>
                        Échecs : <?= (int) $importResult['issue_covers_failed'] ?>.
                    <?php endif; ?>
                    <?php if ((int) ($importResult['issue_covers_remaining'] ?? 0) > 0): ?>
                        <strong><?= (int) $importResult['issue_covers_remaining'] ?> couverture(s) restante(s)</strong>
                        — relancez l’import avec la même revue et « Télécharger les couvertures »
                        (lot de <?= (int) ($importResult['cover_batch_size'] ?? Moncine\MagazineCatalogImporter::DEFAULT_COVER_BATCH_SIZE) ?> max).
                    <?php endif; ?>
                    <?php if (!empty($importResult['cover_batch_reached'])): ?>
                        Limite du lot atteinte pour cette passe.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!$magazineModuleAvailable): ?>
        <p class="alert alert-warning">Le module magazines n’est pas disponible (migrations manquantes).</p>
    <?php else: ?>

    <details class="catalog-admin-panel" open>
        <summary class="catalog-admin-panel__summary">Importer un fichier JSON</summary>
        <div class="catalog-admin-panel__body">
            <form method="post" enctype="multipart/form-data" action="/import-catalogue-magazines.php"
                  class="import-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="import_json">

                <p class="hint">
                    Format attendu : export ABM (<code>format_version</code>, tableau <code>series</code>
                    avec <code>issues</code>). Les URLs de couverture HTTP sont converties en HTTPS.
                </p>

                <label for="json_file">Fichier JSON</label>
                <input type="file" name="json_file" id="json_file" accept=".json,application/json">

                <p class="hint">Ou chemin sur le serveur (si le fichier est trop volumineux pour l’envoi navigateur) :</p>
                <label for="json_path">Chemin serveur</label>
                <input type="text" name="json_path" id="json_path"
                       placeholder="<?= Moncine\View::escape($defaultJsonPath) ?>"
                       value="">

                <label for="magazine_filter">Filtrer une revue (optionnel)</label>
                <input type="text" name="magazine_filter" id="magazine_filter"
                       placeholder="ex. Tilt">

                <fieldset>
                    <legend>Options</legend>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="skip_existing" value="1" checked>
                        Ignorer les numéros déjà présents (même série + même n°)
                    </label>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="dry_run" value="1">
                        Simulation seulement (compter sans écrire)
                    </label>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="download_covers" value="1">
                        Télécharger les couvertures (par lots — URLs HTTPS uniquement)
                    </label>
                    <label for="cover_batch_size">Couvertures par lot</label>
                    <input type="number" name="cover_batch_size" id="cover_batch_size"
                           min="<?= Moncine\MagazineCatalogImporter::MIN_COVER_BATCH_SIZE ?>"
                           max="<?= Moncine\MagazineCatalogImporter::MAX_COVER_BATCH_SIZE ?>"
                           value="<?= Moncine\MagazineCatalogImporter::DEFAULT_COVER_BATCH_SIZE ?>"
                           class="input-narrow">
                    <p class="hint">
                        Maximum de couvertures téléchargées par passage (défaut 20).
                        Pour une revue de 300 numéros, relancez l’import environ 15 fois avec le même JSON
                        et le filtre revue, en laissant « Ignorer les doublons » coché.
                    </p>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Lancer l’import catalogue</button>
                </div>
            </form>
        </div>
    </details>

    <details class="catalog-admin-panel">
        <summary class="catalog-admin-panel__summary">Créer une série catalogue (sans bibliothèque)</summary>
        <div class="catalog-admin-panel__body">
            <p class="hint">
                Utile avant un import partiel ou pour préparer une revue absente du JSON.
                La série sera visible dans le catalogue ; les utilisateurs l’ajouteront à leur collection s’ils le souhaitent.
            </p>
            <form method="post" action="/import-catalogue-magazines.php" class="import-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="create_series">

                <label for="series_titre">Titre de la revue <span class="required">*</span></label>
                <input type="text" name="series_titre" id="series_titre" required maxlength="200">

                <label for="publication_type">Périodicité</label>
                <select name="publication_type" id="publication_type">
                    <?php foreach (Moncine\PublicationType::choices() as $value => $label): ?>
                        <option value="<?= Moncine\View::escape($value) ?>"
                            <?= $value === 'mensuel' ? ' selected' : '' ?>>
                            <?= Moncine\View::escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="editeur">Éditeur</label>
                <input type="text" name="editeur" id="editeur" maxlength="120">

                <label for="notes">Notes internes</label>
                <input type="text" name="notes" id="notes" maxlength="500"
                       placeholder="ex. abm_magazine_id=29">

                <button type="submit" class="btn btn-secondary">Créer la série catalogue</button>
            </form>
        </div>
    </details>

    <?php endif; ?>

    <p class="collection-page__footer-links">
        <a href="/catalogue.php">← Catalogue</a>
        <a href="/import.php">Importer / exporter</a>
        <a href="/maintenance-catalogue.php">Maintenance catalogue</a>
    </p>
</section>
