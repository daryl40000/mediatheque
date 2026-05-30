<?php
/** @var string $error */
/** @var bool $requiresApproval */
?>
<section class="auth-page">
    <h1>Créer un compte</h1>
    <p class="lead">
        Remplissez le formulaire ci-dessous. Un e-mail de confirmation vous sera envoyé.
        <?php if ($requiresApproval): ?>
            Après confirmation, un administrateur devra valider votre compte avant la connexion.
        <?php else: ?>
            Après confirmation de l’e-mail, vous pourrez vous connecter.
        <?php endif; ?>
    </p>

    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/inscription.php" class="auth-form import-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>

        <label for="reg_prenom">Prénom</label>
        <input type="text" name="prenom" id="reg_prenom" autocomplete="given-name">

        <label for="reg_nom">Nom</label>
        <input type="text" name="nom" id="reg_nom" required autocomplete="family-name">

        <label for="reg_pseudo">Pseudo (optionnel)</label>
        <input type="text" name="pseudo" id="reg_pseudo" autocomplete="nickname"
               maxlength="<?= Moncine\UserProfile::MAX_PSEUDO_LENGTH ?>">

        <label for="reg_email">Adresse e-mail</label>
        <input type="email" name="email" id="reg_email" required autocomplete="email">

        <label for="reg_password">Mot de passe</label>
        <input type="password" name="password" id="reg_password" required autocomplete="new-password"
               minlength="<?= Moncine\UtilisateurRepository::MIN_PASSWORD_LENGTH ?>"
               maxlength="<?= Moncine\UtilisateurRepository::MAX_PASSWORD_LENGTH ?>">

        <label for="reg_password_confirm">Confirmer le mot de passe</label>
        <input type="password" name="password_confirm" id="reg_password_confirm" required autocomplete="new-password"
               minlength="<?= Moncine\UtilisateurRepository::MIN_PASSWORD_LENGTH ?>"
               maxlength="<?= Moncine\UtilisateurRepository::MAX_PASSWORD_LENGTH ?>">

        <button type="submit" class="btn btn-primary">Envoyer la demande</button>
    </form>

    <p class="hint"><a href="/connexion.php">Déjà un compte ? Se connecter</a></p>
</section>
<script>
(function () {
    var form = document.querySelector('.auth-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        var a = document.getElementById('reg_password');
        var b = document.getElementById('reg_password_confirm');
        if (a && b && a.value !== b.value) {
            e.preventDefault();
            alert('Les deux mots de passe ne correspondent pas.');
        }
    });
})();
</script>
