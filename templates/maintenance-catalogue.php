<?php
/**
 * @var array<string, int> $stats
 * @var list<array<string, mixed>> $duplicateTitleGroups
 * @var list<array<string, mixed>> $duplicateTmdbGroups
 * @var list<array<string, mixed>> $incompleteOeuvres
 * @var list<string> $orphanPosters
 * @var list<array<string, mixed>> $auditLog
 * @var string $message
 * @var string $error
 * @var bool $dbBackupSqliteOk
 * @var int $dbBackupMaxMb
 */
?>
<section class="catalog-maintenance-page">
    <div class="catalog-admin-page__head">
        <div>
            <h1>Maintenance du catalogue</h1>
            <p class="lead">
                Détectez les doublons, fusionnez les fiches, nettoyez les affiches orphelines
                et consultez le journal des actions admin.
            </p>
            <p class="hint"><a href="/maintenance-medias.php">Stockage médias (PDF, exports…) →</a></p>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($message) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <section class="catalog-maintenance-stats">
        <h2 class="visually-hidden">Vue d’ensemble</h2>
        <ul class="catalog-maintenance-stats__grid">
            <li class="catalog-maintenance-stat">
                <span class="catalog-maintenance-stat__value"><?= (int) $stats['total_oeuvres'] ?></span>
                <span class="catalog-maintenance-stat__label">Œuvres au catalogue</span>
            </li>
            <li class="catalog-maintenance-stat<?= (int) $stats['duplicate_title_groups'] > 0 ? ' catalog-maintenance-stat--warn' : '' ?>">
                <span class="catalog-maintenance-stat__value"><?= (int) $stats['duplicate_title_groups'] ?></span>
                <span class="catalog-maintenance-stat__label">Groupes doublons (titre)</span>
            </li>
            <li class="catalog-maintenance-stat<?= (int) $stats['duplicate_tmdb_groups'] > 0 ? ' catalog-maintenance-stat--warn' : '' ?>">
                <span class="catalog-maintenance-stat__value"><?= (int) $stats['duplicate_tmdb_groups'] ?></span>
                <span class="catalog-maintenance-stat__label">Doublons TMDB</span>
            </li>
            <li class="catalog-maintenance-stat">
                <span class="catalog-maintenance-stat__value"><?= (int) $stats['incomplete_count'] ?></span>
                <span class="catalog-maintenance-stat__label">Fiches incomplètes</span>
            </li>
            <li class="catalog-maintenance-stat<?= (int) $stats['orphan_posters'] > 0 ? ' catalog-maintenance-stat--warn' : '' ?>">
                <span class="catalog-maintenance-stat__value"><?= (int) $stats['orphan_posters'] ?></span>
                <span class="catalog-maintenance-stat__label">Affiches orphelines</span>
            </li>
        </ul>
    </section>

    <section class="catalog-maintenance-panel">
        <h2>Doublons (titre + réalisateur)</h2>
        <p class="hint">Regroupement insensible à la casse et aux espaces en trop.</p>
        <?php if ($duplicateTitleGroups === []): ?>
            <p class="alert alert-info">Aucun doublon détecté sur le titre et le réalisateur.</p>
        <?php else: ?>
            <?php foreach ($duplicateTitleGroups as $group): ?>
                <article class="catalog-maintenance-duplicate">
                    <h3>
                        <?= Moncine\View::escape((string) ($group['titre'] ?? '')) ?>
                        <?php if (trim((string) ($group['realisateur'] ?? '')) !== ''): ?>
                            <span class="hint">— <?= Moncine\View::escape((string) $group['realisateur']) ?></span>
                        <?php endif; ?>
                    </h3>
                    <p class="hint">IDs : <?= Moncine\View::escape(implode(', ', array_map('strval', $group['ids'] ?? []))) ?></p>
                    <form method="post" class="catalog-maintenance-merge-form import-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="action" value="merge_oeuvres">
                        <label for="keep_title_<?= Moncine\View::escape((string) ($group['key'] ?? '')) ?>">Conserver</label>
                        <select name="keep_id" id="keep_title_<?= Moncine\View::escape((string) ($group['key'] ?? '')) ?>" required>
                            <?php foreach ($group['ids'] as $id): ?>
                                <option value="<?= (int) $id ?>">#<?= (int) $id ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="remove_title_<?= Moncine\View::escape((string) ($group['key'] ?? '')) ?>">Fusionner / supprimer</label>
                        <select name="remove_id" id="remove_title_<?= Moncine\View::escape((string) ($group['key'] ?? '')) ?>" required>
                            <?php foreach ($group['ids'] as $id): ?>
                                <option value="<?= (int) $id ?>">#<?= (int) $id ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm"
                                onclick="return confirm('Fusionner ces deux fiches ? Les bibliothèques utilisateur seront conservées.');">
                            Fusionner
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="catalog-maintenance-panel">
        <h2>Doublons TMDB</h2>
        <p class="hint">Plusieurs fiches catalogue partagent le même identifiant TMDB.</p>
        <?php if ($duplicateTmdbGroups === []): ?>
            <p class="alert alert-info">Aucun doublon TMDB.</p>
        <?php else: ?>
            <?php foreach ($duplicateTmdbGroups as $group): ?>
                <article class="catalog-maintenance-duplicate">
                    <h3>TMDB #<?= (int) ($group['tmdb_id'] ?? 0) ?></h3>
                    <p class="hint">IDs catalogue : <?= Moncine\View::escape(implode(', ', array_map('strval', $group['ids'] ?? []))) ?></p>
                    <form method="post" class="catalog-maintenance-merge-form import-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="action" value="merge_oeuvres">
                        <label>Conserver</label>
                        <select name="keep_id" required>
                            <?php foreach ($group['ids'] as $id): ?>
                                <option value="<?= (int) $id ?>">#<?= (int) $id ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Fusionner / supprimer</label>
                        <select name="remove_id" required>
                            <?php foreach ($group['ids'] as $id): ?>
                                <option value="<?= (int) $id ?>">#<?= (int) $id ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm"
                                onclick="return confirm('Fusionner ces deux fiches TMDB ?');">
                            Fusionner
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="catalog-maintenance-panel">
        <h2>Fiches incomplètes</h2>
        <p class="hint">Sans synopsis, sans affiche et sans identifiant TMDB.</p>
        <?php if ($incompleteOeuvres === []): ?>
            <p class="alert alert-info">Aucune fiche incomplète repérée (limite : 80 affichées).</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="films-table catalog-admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Titre</th>
                            <th>Réalisateur</th>
                            <th>Bibliothèques</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incompleteOeuvres as $oeuvre): ?>
                            <tr>
                                <td><?= (int) ($oeuvre['id'] ?? 0) ?></td>
                                <td><?= Moncine\View::escape((string) ($oeuvre['titre'] ?? '')) ?></td>
                                <td><?= Moncine\View::escape((string) ($oeuvre['realisateur'] ?? '')) ?></td>
                                <td><?= (int) ($oeuvre['library_count'] ?? 0) ?></td>
                                <td>
                                    <a href="<?= Moncine\View::escape(Moncine\View::oeuvreUrl((int) ($oeuvre['id'] ?? 0))) ?>"
                                       class="btn btn-sm btn-secondary">Compléter</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="catalog-maintenance-panel">
        <h2>Affiches orphelines</h2>
        <p class="hint">
            Fichiers dans <code>www/posters/</code> non référencés par une fiche catalogue.
        </p>
        <?php if ($orphanPosters === []): ?>
            <p class="alert alert-info">Aucune affiche orpheline.</p>
        <?php else: ?>
            <ul class="catalog-maintenance-orphans">
                <?php foreach (array_slice($orphanPosters, 0, 30) as $path): ?>
                    <li><code><?= Moncine\View::escape(basename($path)) ?></code></li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($orphanPosters) > 30): ?>
                <p class="hint">… et <?= count($orphanPosters) - 30 ?> autre(s) fichier(s).</p>
            <?php endif; ?>
            <form method="post" class="inline-form"
                  onsubmit="return confirm('Supprimer <?= count($orphanPosters) ?> affiche(s) orpheline(s) ?');">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="purge_orphan_posters">
                <button type="submit" class="btn btn-danger">Supprimer les affiches orphelines</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="catalog-maintenance-panel">
        <h2>Fusion manuelle</h2>
        <form method="post" class="catalog-maintenance-merge-form import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="merge_oeuvres">
            <label for="merge_keep_id">Conserver la fiche n°</label>
            <input type="number" name="keep_id" id="merge_keep_id" min="1" required>
            <label for="merge_remove_id">Fusionner / supprimer la fiche n°</label>
            <input type="number" name="remove_id" id="merge_remove_id" min="1" required>
            <button type="submit" class="btn btn-secondary"
                    onclick="return confirm('Confirmer la fusion de ces deux fiches ?');">
                Fusionner
            </button>
        </form>
    </section>

    <section class="catalog-maintenance-panel catalog-maintenance-panel--danger">
        <h2>Sauvegarde et restauration de la base</h2>
        <p class="hint">
            Exporte ou remplace l’intégralité de <code>moncine.db</code> : catalogue, bibliothèques,
            utilisateurs, historique, envies, groupes, etc. Les fichiers hors base
            (clé TMDB, affiches dans <code>data/</code>) ne sont pas inclus.
        </p>
        <?php if (!$dbBackupSqliteOk): ?>
            <p class="alert alert-warning">
                L’extension PHP <strong>SQLite3</strong> est requise sur le serveur pour cette fonctionnalité.
            </p>
        <?php else: ?>
            <div class="catalog-maintenance-db-backup">
                <article class="catalog-maintenance-db-backup__block">
                    <h3>Télécharger une sauvegarde</h3>
                    <p class="hint">
                        Fichier <strong>.db</strong> (SQLite). Réservé à l’administrateur connecté :
                        mot de passe demandé, jeton anti-CSRF, limite de fréquence.
                    </p>
                    <form method="post" action="/admin-export-base.php" class="import-form catalog-maintenance-db-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <label for="export_admin_password">Votre mot de passe administrateur</label>
                        <input type="password" name="admin_password" id="export_admin_password"
                               autocomplete="current-password" required minlength="8">
                        <button type="submit" class="btn btn-secondary">Télécharger la base complète</button>
                    </form>
                </article>

                <article class="catalog-maintenance-db-backup__block">
                    <h3>Restaurer depuis une sauvegarde</h3>
                    <p class="alert alert-warning">
                        <strong>Danger :</strong> remplace toute la base actuelle. Conservez une copie avant.
                        Une sauvegarde automatique de l’ancienne base est créée dans
                        <code>data/db_snapshots/</code>.
                    </p>
                    <form method="post" enctype="multipart/form-data"
                          class="import-form catalog-maintenance-db-form"
                          onsubmit="return confirm('Remplacer TOUTE la base Moncine par ce fichier ? Cette action est irréversible sans votre sauvegarde.');">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="action" value="restore_database">
                        <label for="backup_file">Fichier de sauvegarde (.db)</label>
                        <input type="file" name="backup_file" id="backup_file" accept=".db,application/octet-stream"
                               required>
                        <p class="hint">Taille max. <?= (int) $dbBackupMaxMb ?> Mo.</p>
                        <label for="restore_admin_password">Votre mot de passe administrateur</label>
                        <input type="password" name="admin_password" id="restore_admin_password"
                               autocomplete="current-password" required minlength="8">
                        <label class="catalog-maintenance-db-confirm">
                            <input type="checkbox" name="confirm_restore" value="1" required>
                            Je comprends que toutes les données actuelles seront remplacées.
                        </label>
                        <label for="confirm_phrase">Saisir <strong>RESTAURER</strong> pour confirmer</label>
                        <input type="text" name="confirm_phrase" id="confirm_phrase" required
                               pattern="RESTAURER" autocomplete="off" spellcheck="false"
                               title="Saisissez RESTAURER en majuscules">
                        <button type="submit" class="btn btn-danger">Restaurer la base</button>
                    </form>
                </article>
            </div>
        <?php endif; ?>
    </section>

    <section class="catalog-maintenance-panel">
        <h2>Journal des actions</h2>
        <?php if ($auditLog === []): ?>
            <p class="hint">Aucune action enregistrée pour l’instant.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="films-table catalog-admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Œuvre</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLog as $entry): ?>
                            <tr>
                                <td><?= Moncine\View::escape((string) ($entry['created_at'] ?? '')) ?></td>
                                <td><?= Moncine\View::escape((string) ($entry['user_nom'] ?? '')) ?></td>
                                <td><?= Moncine\View::escape(Moncine\CatalogAuditLog::actionLabel((string) ($entry['action'] ?? ''))) ?></td>
                                <td><?= (int) ($entry['oeuvre_id'] ?? 0) > 0 ? '#' . (int) $entry['oeuvre_id'] : '—' ?></td>
                                <td><?= Moncine\View::escape((string) ($entry['details'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <p class="collection-page__footer-links">
        <a href="/catalogue.php">← Catalogue</a>
        <a href="/ranger-affiches.php">Affiches TMDB</a>
        <a href="/import.php">Importer / exporter</a>
    </p>
</section>
