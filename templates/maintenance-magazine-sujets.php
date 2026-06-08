<?php
/**
 * @var array{total: int, orphan_count: int, duplicate_groups: int} $stats
 * @var list<array<string, mixed>> $orphanSubjects
 * @var list<array<string, mixed>> $duplicateGroups
 * @var string $message
 * @var string $error
 * @var string $moduleError
 */
?>
<section class="catalog-maintenance-page">
    <div class="catalog-admin-page__head">
        <div>
            <h1>Sujets magazines — maintenance</h1>
            <p class="lead">
                Nettoyez les sujets créés par erreur (faute de frappe, saisie abandonnée)
                et fusionnez les doublons proches (« After Life » / « Afterlife »).
            </p>
            <p class="hint">
                <a href="/maintenance-catalogue.php">← Maintenance catalogue</a>
                · <a href="/magazines-recherche.php">Recherche par sujet</a>
            </p>
        </div>
    </div>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php else: ?>
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
                    <span class="catalog-maintenance-stat__value"><?= (int) $stats['total'] ?></span>
                    <span class="catalog-maintenance-stat__label">Sujets au catalogue</span>
                </li>
                <li class="catalog-maintenance-stat<?= (int) $stats['orphan_count'] > 0 ? ' catalog-maintenance-stat--warn' : '' ?>">
                    <span class="catalog-maintenance-stat__value"><?= (int) $stats['orphan_count'] ?></span>
                    <span class="catalog-maintenance-stat__label">Orphelins (aucun numéro)</span>
                </li>
                <li class="catalog-maintenance-stat<?= (int) $stats['duplicate_groups'] > 0 ? ' catalog-maintenance-stat--warn' : '' ?>">
                    <span class="catalog-maintenance-stat__value"><?= (int) $stats['duplicate_groups'] ?></span>
                    <span class="catalog-maintenance-stat__label">Groupes de doublons</span>
                </li>
            </ul>
        </section>

        <section class="catalog-maintenance-panel">
            <h2>Sujets orphelins</h2>
            <p class="hint">
                Sujets qui ne sont liés à <strong>aucun numéro</strong>.
                Souvent créés après une faute de frappe puis remplacés par un autre libellé.
            </p>
            <?php if ($orphanSubjects === []): ?>
                <p class="alert alert-info">Aucun sujet orphelin — rien à nettoyer.</p>
            <?php else: ?>
                <form method="post" class="inline-form catalog-maintenance-bulk">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="purge_orphans">
                    <button type="submit" class="btn btn-secondary"
                            onclick="return confirm('Supprimer tous les sujets orphelins listés (<?= count($orphanSubjects) ?>) ?');">
                        Tout supprimer (<?= count($orphanSubjects) ?>)
                    </button>
                </form>
                <table class="catalog-maintenance-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sujet</th>
                            <th>Créé le</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orphanSubjects as $subject): ?>
                            <?php $subjectId = (int) ($subject['id'] ?? 0); ?>
                            <tr<?= !empty($subject['is_empty_label']) ? ' class="catalog-maintenance-table__warn"' : '' ?>>
                                <td>#<?= $subjectId ?></td>
                                <td>
                                    <span class="magazine-tag magazine-tag--subject">
                                        <?= Moncine\View::escape((string) ($subject['category_label'] ?? '')) ?>
                                    </span>
                                    <?php if (!empty($subject['is_empty_label'])): ?>
                                        <strong class="catalog-maintenance-table__empty">(libellé vide)</strong>
                                    <?php else: ?>
                                        <strong><?= Moncine\View::escape((string) ($subject['display_label'] ?? '')) ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><?= Moncine\View::escape((string) ($subject['created_at'] ?? '')) ?></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                        <input type="hidden" name="action" value="delete_orphan">
                                        <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm"
                                                onclick="return confirm('Supprimer ce sujet orphelin ?');">
                                            Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="catalog-maintenance-panel">
            <h2>Doublons probables</h2>
            <p class="hint">
                Même catégorie, tag, année et libellé normalisé (espaces / ponctuation ignorés).
                Fusionnez vers la fiche à conserver : les numéros liés au doublon seront réaffectés.
            </p>
            <?php if ($duplicateGroups === []): ?>
                <p class="alert alert-info">Aucun groupe de doublons détecté.</p>
            <?php else: ?>
                <?php foreach ($duplicateGroups as $group): ?>
                    <?php $subjects = $group['subjects'] ?? []; ?>
                    <article class="catalog-maintenance-duplicate">
                        <h3>Groupe <?= Moncine\View::escape((string) ($group['key'] ?? '')) ?></h3>
                        <ul class="catalog-maintenance-duplicate__list">
                            <?php foreach ($subjects as $subject): ?>
                                <li>
                                    #<?= (int) ($subject['id'] ?? 0) ?> —
                                    <strong><?= Moncine\View::escape((string) ($subject['display_label'] ?? '')) ?></strong>
                                    <span class="hint">
                                        (<?= (int) ($subject['usage_count'] ?? 0) ?> numéro<?= (int) ($subject['usage_count'] ?? 0) > 1 ? 's' : '' ?>)
                                    </span>
                                    <?php $linkSubjectId = (int) ($subject['id'] ?? 0); ?>
                                    <?php if ($linkSubjectId > 0): ?>
                                        · <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectUrl($linkSubjectId)) ?>">Voir</a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <form method="post" class="catalog-maintenance-merge-form import-form">
                            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                            <input type="hidden" name="action" value="merge_subjects">
                            <label for="keep_<?= Moncine\View::escape((string) ($group['key'] ?? '')) ?>">Conserver</label>
                            <select name="keep_id" id="keep_<?= Moncine\View::escape((string) ($group['key'] ?? '')) ?>" required>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= (int) ($subject['id'] ?? 0) ?>">
                                        #<?= (int) ($subject['id'] ?? 0) ?> — <?= Moncine\View::escape((string) ($subject['display_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="remove_<?= Moncine\View::escape((string) ($group['key'] ?? '')) ?>">Fusionner (supprimer)</label>
                            <select name="remove_id" id="remove_<?= Moncine\View::escape((string) ($group['key'] ?? '')) ?>" required>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= (int) ($subject['id'] ?? 0) ?>">
                                        #<?= (int) ($subject['id'] ?? 0) ?> — <?= Moncine\View::escape((string) ($subject['display_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-secondary btn-sm"
                                    onclick="return confirm('Fusionner ces sujets ? Les numéros seront réaffectés.');">
                                Fusionner
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
