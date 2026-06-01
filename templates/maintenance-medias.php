<?php
/**
 * @var string $message
 * @var string $error
 * @var string $defaultRoot
 * @var string $effectiveRoot
 * @var string $envRoot
 * @var bool $hasOverride
 * @var list<string> $subdirs
 * @var int $storedCount
 * @var string $uploadLimitsWarning
 * @var string $uploadMaxLabel
 * @var string $postMaxLabel
 * @var string $pdfMaxLabel
 */
?>
<section class="catalog-maintenance-page">
    <div class="catalog-admin-page__head">
        <div>
            <h1>Stockage médias</h1>
            <p class="lead">
                Fichiers volumineux (PDF magazines, livres, exports…) stockés <strong>hors</strong> le dossier web.
                Les fichiers ne sont pas accessibles par une URL directe : lecture via Moncine uniquement.
            </p>
        </div>
        <p class="hint">
            <a href="/maintenance-catalogue.php">← Maintenance catalogue</a>
        </p>
    </div>

    <?php if ($message !== ''): ?>
        <p class="alert alert-success"><?= nl2br(Moncine\View::escape($message)) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= nl2br(Moncine\View::escape($error)) ?></p>
    <?php endif; ?>

    <section class="catalog-maintenance-panel">
        <h2>Racine des médias</h2>
        <dl class="catalog-maintenance-dl">
            <dt>Variable d’environnement <code>MONCINE_MEDIA_PATH</code></dt>
            <dd><code><?= Moncine\View::escape($envRoot) ?></code></dd>
            <dt>Racine effective (utilisée par Moncine)</dt>
            <dd><code><?= Moncine\View::escape($effectiveRoot) ?></code>
                <?php if ($hasOverride): ?>
                    <span class="hint">(surcharge enregistrée en base)</span>
                <?php else: ?>
                    <span class="hint">(défaut ou variable d’environnement)</span>
                <?php endif; ?>
            </dd>
            <dt>Fichiers référencés en base</dt>
            <dd><?= (int) $storedCount ?></dd>
        </dl>

        <form method="post" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="save_media_root">
            <label for="media_root">Chemin absolu de la racine (ex. share YunoHost)</label>
            <input type="text" id="media_root" name="media_root" class="input-wide"
                   value="<?= Moncine\View::escape($effectiveRoot) ?>" required
                   autocomplete="off" spellcheck="false">
            <p class="hint">
                Sur YunoHost, un dossier partagé type
                <code>/home/yunohost.multimedia/share/moncine</code> convient souvent.
                L’utilisateur du serveur web doit pouvoir lire et écrire ce dossier.
                Le chemin doit être <strong>absolu</strong> ; les dossiers système (<code>/etc</code>, etc.) sont refusés.
            </p>
            <div class="form-actions">
                <button type="submit" class="btn">Enregistrer la racine</button>
            </div>
        </form>

        <?php if ($hasOverride): ?>
            <form method="post" class="inline-form" style="margin-top: 1rem;">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="reset_media_root">
                <button type="submit" class="btn btn-secondary">Revenir au chemin par défaut</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="catalog-maintenance-panel">
        <h2>Limites d’envoi PHP (PDF magazines)</h2>
        <dl class="catalog-maintenance-dl">
            <dt>upload_max_filesize</dt>
            <dd><code><?= Moncine\View::escape($uploadMaxLabel) ?></code></dd>
            <dt>post_max_size</dt>
            <dd><code><?= Moncine\View::escape($postMaxLabel) ?></code></dd>
            <dt>Maximum application (PDF)</dt>
            <dd><code><?= Moncine\View::escape($pdfMaxLabel) ?></code></dd>
        </dl>
        <?php if ($uploadLimitsWarning !== ''): ?>
            <div class="alert alert-danger"><?= $uploadLimitsWarning ?></div>
        <?php else: ?>
            <p class="alert alert-success">Les limites PHP permettent d’importer des PDF magazines.</p>
        <?php endif; ?>
        <p class="hint">En local : <code>./start-dev.sh</code> ou <code>./www/serve.sh</code> depuis le dossier du projet.</p>
    </section>

    <section class="catalog-maintenance-panel">
        <h2>Sous-dossiers</h2>
        <p class="hint">Créés automatiquement sous la racine :</p>
        <ul>
            <?php foreach ($subdirs as $subdir): ?>
                <li><code><?= Moncine\View::escape($subdir) ?>/</code></li>
            <?php endforeach; ?>
        </ul>
        <form method="post" class="inline-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="ensure_layout">
            <button type="submit" class="btn btn-secondary">Créer les sous-dossiers maintenant</button>
        </form>
    </section>

    <section class="catalog-maintenance-panel">
        <h2>Test lecture / écriture</h2>
        <p class="hint">
            Écrit un petit fichier de test dans <code>tmp/</code>, le relit puis le supprime.
        </p>
        <form method="post">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="self_test">
            <button type="submit" class="btn">Lancer le test</button>
        </form>
    </section>

    <section class="catalog-maintenance-panel">
        <h2>Déploiement YunoHost</h2>
        <ul>
            <li>Définir <code>MONCINE_MEDIA_PATH</code> dans la config de l’app ou via la page ci-dessus.</li>
            <li>Vérifier les droits Unix : le processus PHP (souvent <code>www-data</code>) doit pouvoir écrire dans le share.</li>
            <li>Inclure ce dossier dans vos sauvegardes (en plus de <code>data/moncine.db</code>).</li>
        </ul>
    </section>
</section>
