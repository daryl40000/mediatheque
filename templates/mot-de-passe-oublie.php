<?php
/** @var string $error */
/** @var bool $sent */
?>
<section class="auth-page">
    <h1>Mot de passe oublié</h1>

    <?php if ($sent): ?>
        <p class="alert alert-success">
            Si un compte actif correspond à cette adresse, un e-mail avec un lien de réinitialisation
            a été envoyé. Pensez à vérifier les courriers indésirables.
        </p>
        <p><a href="/connexion.php">Retour à la connexion</a></p>
    <?php else: ?>
        <p class="lead">
            Saisissez votre adresse e-mail. Vous recevrez un lien valable une heure pour choisir un nouveau mot de passe.
        </p>

        <?php if ($error !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
        <?php endif; ?>

        <form method="post" action="/mot-de-passe-oublie.php" class="auth-form import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>

            <label for="reset_email">Adresse e-mail</label>
            <input type="email" name="email" id="reset_email" required autocomplete="email" autofocus>

            <button type="submit" class="btn btn-primary">Envoyer le lien</button>
        </form>

        <p class="hint"><a href="/connexion.php">← Retour à la connexion</a></p>
    <?php endif; ?>
</section>
