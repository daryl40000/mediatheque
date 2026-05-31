<?php
/** @var string $message */
/** @var list<string> $errors */
/** @var int $remoteCount */
/** @var int $localCount */
?>
<section>
    <h1>Affiches sur le serveur</h1>
    <p class="lead">
        Les affiches sont copiées dans le dossier de données Moncine (<code>posters/</code>, à côté de la base).
        Les pages affichent les images via <code>poster.php</code> — plus besoin de charger TMDB à chaque visite.
    </p>

    <?php if ($message !== ''): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($message) ?></p>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="alert alert-warning">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= Moncine\View::escape($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <p class="stats">
        <?= (int) $localCount ?> affiche(s) déjà locale(s) —
        <?= (int) $remoteCount ?> encore sur Internet (TMDB ou autre HTTPS)
    </p>

    <?php if ($remoteCount > 0): ?>
        <form method="post" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <p class="hint">
                Chaque clic télécharge environ 15 affiches (pour ne pas surcharger le serveur).
                Les nouveaux enrichissements TMDB sont enregistrés automatiquement en local.
            </p>
            <button type="submit" class="btn btn-primary">Télécharger 15 affiches</button>
        </form>
    <?php else: ?>
        <p class="alert alert-success">Toutes les affiches connues sont déjà hébergées localement.</p>
    <?php endif; ?>

    <p class="collection-page__footer-links">
        <a href="/import.php">← Retour à Importer</a>
    </p>
</section>
