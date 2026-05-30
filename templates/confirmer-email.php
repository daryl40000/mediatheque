<?php
/** @var string $outcome */
/** @var string $message */
?>
<section class="auth-page">
    <h1>Confirmation e-mail</h1>
    <p class="alert alert-warning"><?= Moncine\View::escape($message) ?></p>
    <p class="hint"><a href="/connexion.php">Retour à la connexion</a></p>
</section>
