<?php
/**
 * @var string $token
 * @var bool $tokenValid
 * @var string $error
 * @var bool $success
 */
$minLen = Moncine\UtilisateurRepository::MIN_PASSWORD_LENGTH;
$maxLen = Moncine\UtilisateurRepository::MAX_PASSWORD_LENGTH;
?>
<section class="auth-page">
    <h1>Nouveau mot de passe</h1>

    <?php if ($success): ?>
        <p class="alert alert-success">Votre mot de passe a été mis à jour. Vous pouvez vous connecter.</p>
        <p><a href="/connexion.php" class="btn btn-primary">Se connecter</a></p>
    <?php elseif (!$tokenValid): ?>
        <p class="alert alert-warning">Ce lien est invalide, expiré ou déjà utilisé.</p>
        <p><a href="/mot-de-passe-oublie.php">Demander un nouveau lien</a></p>
    <?php else: ?>
        <p class="lead">Choisissez un nouveau mot de passe (<?= $minLen ?> caractères minimum).</p>

        <?php if ($error !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
        <?php endif; ?>

        <form method="post" action="/reinitialiser-mot-de-passe.php" class="auth-form import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="token" value="<?= Moncine\View::escape($token) ?>">

            <label for="reset_password">Nouveau mot de passe</label>
            <input type="password" name="password" id="reset_password" required autocomplete="new-password"
                   minlength="<?= $minLen ?>" maxlength="<?= $maxLen ?>">

            <label for="reset_password2">Confirmer</label>
            <input type="password" name="password_confirm" id="reset_password2" required autocomplete="new-password"
                   minlength="<?= $minLen ?>" maxlength="<?= $maxLen ?>">

            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    <?php endif; ?>
</section>
