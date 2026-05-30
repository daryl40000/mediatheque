<?php
/**
 * @var array<string, mixed> $user
 * @var array<string, mixed>|null $foyer
 * @var string $error
 * @var string $success
 */
$minLen = Moncine\UtilisateurRepository::MIN_PASSWORD_LENGTH;
$maxLen = Moncine\UtilisateurRepository::MAX_PASSWORD_LENGTH;
?>
<section class="account-page">
    <h1>Mon compte</h1>
    <p class="lead">Modifiez votre nom, votre e-mail ou votre mot de passe.</p>

    <?php if ($success !== ''): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <details class="catalog-admin-panel" open>
        <summary class="catalog-admin-panel__summary">Profil</summary>
        <div class="catalog-admin-panel__body">
            <form method="post" action="/mon-compte.php" class="import-form auth-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="profile">

                <label for="account_nom">Nom</label>
                <input type="text" name="nom" id="account_nom" required
                       value="<?= Moncine\View::escape((string) ($user['nom'] ?? '')) ?>">

                <label for="account_email">E-mail</label>
                <input type="email" name="email" id="account_email" required autocomplete="email"
                       value="<?= Moncine\View::escape((string) ($user['email'] ?? '')) ?>">

                <p class="hint">Rôle : <?= Moncine\View::escape(Moncine\UserRole::label((string) ($user['role'] ?? ''))) ?></p>
                <?php if ($foyer !== null): ?>
                    <p class="hint">Foyer : <?= Moncine\View::escape((string) ($foyer['nom'] ?? '')) ?>
                        — collection partagée avec les autres membres.</p>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Enregistrer le profil</button>
            </form>
        </div>
    </details>

    <details class="catalog-admin-panel">
        <summary class="catalog-admin-panel__summary">Changer le mot de passe</summary>
        <div class="catalog-admin-panel__body">
            <form method="post" action="/mon-compte.php" class="import-form auth-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="password">

                <label for="current_password">Mot de passe actuel</label>
                <input type="password" name="current_password" id="current_password" required
                       autocomplete="current-password" maxlength="<?= $maxLen ?>">

                <label for="new_password">Nouveau mot de passe</label>
                <input type="password" name="new_password" id="new_password" required
                       autocomplete="new-password" minlength="<?= $minLen ?>" maxlength="<?= $maxLen ?>">

                <label for="new_password_confirm">Confirmer le nouveau mot de passe</label>
                <input type="password" name="new_password_confirm" id="new_password_confirm" required
                       autocomplete="new-password" minlength="<?= $minLen ?>" maxlength="<?= $maxLen ?>">

                <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
            </form>
        </div>
    </details>

    <p class="collection-page__footer-links">
        <a href="/">← Accueil</a>
    </p>
</section>
