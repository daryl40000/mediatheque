<?php
/**
 * @var array<string, mixed> $user
 * @var string $displayName
 * @var array<string, mixed>|null $foyer
 * @var string $error
 * @var string $success
 * @var int $maxPseudoLength
 * @var int $maxVilleLength
 * @var bool $isSearchable
 * @var bool $canDeleteAccount
 * @var bool $isSoloGroupMember
 * @var bool $steamModuleReady
 */
$minLen = Moncine\UtilisateurRepository::MIN_PASSWORD_LENGTH;
$maxLen = Moncine\UtilisateurRepository::MAX_PASSWORD_LENGTH;
?>
<section class="account-page">
    <h1>Mon compte</h1>
    <p class="lead">
        <strong><?= Moncine\View::escape($displayName) ?></strong>
    </p>

    <?php if ($success !== ''): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <details class="catalog-admin-panel" open>
        <summary class="catalog-admin-panel__summary">Informations du compte</summary>
        <div class="catalog-admin-panel__body">
            <form method="post" action="/parametres.php" class="import-form auth-form account-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="profile">

                <?php
                unset($info, $infoHtml, $infoAria);
                $for = 'account_prenom';
                $label = 'Prénom';
                require MONCINE_ROOT . '/templates/_form_label_info.php';
                ?>
                <input type="text" name="prenom" id="account_prenom" autocomplete="given-name"
                       value="<?= Moncine\View::escape((string) ($user['prenom'] ?? '')) ?>">

                <?php
                unset($info, $infoHtml, $infoAria);
                $for = 'account_nom';
                $label = 'Nom';
                require MONCINE_ROOT . '/templates/_form_label_info.php';
                ?>
                <input type="text" name="nom" id="account_nom" autocomplete="family-name"
                       value="<?= Moncine\View::escape((string) ($user['nom'] ?? '')) ?>">

                <?php
                unset($info, $infoHtml, $infoAria);
                $for = 'account_pseudo';
                $label = 'Pseudo';
                $info = 'Optionnel. Affiché à la place du prénom et du nom dans l’application. Vous pouvez aussi l’utiliser pour vous connecter à la place de l’e-mail.';
                $infoAria = 'Aide sur le pseudo';
                require MONCINE_ROOT . '/templates/_form_label_info.php';
                unset($info, $infoAria);
                ?>
                <input type="text" name="pseudo" id="account_pseudo" autocomplete="nickname"
                       maxlength="<?= (int) $maxPseudoLength ?>"
                       value="<?= Moncine\View::escape((string) ($user['pseudo'] ?? '')) ?>">

                <?php
                unset($info, $infoHtml, $infoAria);
                $for = 'account_ville';
                $label = 'Ville';
                $info = 'Optionnel. Sert à la recherche d’utilisateurs par ville (si vous acceptez d’apparaître dans les résultats).';
                $infoAria = 'Aide sur la ville';
                require MONCINE_ROOT . '/templates/_form_label_info.php';
                unset($info, $infoAria);
                ?>
                <input type="text" name="ville" id="account_ville" autocomplete="address-level2"
                       maxlength="<?= (int) $maxVilleLength ?>"
                       value="<?= Moncine\View::escape((string) ($user['ville'] ?? '')) ?>">

                <label class="checkbox-label checkbox-label--with-info">
                    <input type="hidden" name="searchable" value="0">
                    <input type="checkbox" name="searchable" value="1"
                           <?= $isSearchable ? ' checked' : '' ?>>
                    <span class="checkbox-label__text">
                        Apparaître dans la recherche d’utilisateurs
                        <span class="info-tooltip" tabindex="0"
                              aria-label="Visibilité dans la recherche d’utilisateurs">
                            <span class="info-tooltip__icon" aria-hidden="true">i</span>
                            <span class="info-tooltip__popup" role="tooltip">
                                Les autres membres peuvent vous trouver par pseudo et ville.
                                Décochez pour rester invisible dans la recherche.
                            </span>
                        </span>
                    </span>
                </label>

                <?php if (!empty($steamModuleReady)): ?>
                <?php
                unset($info, $infoHtml, $infoAria);
                $for = 'account_steam_id';
                $label = 'SteamID64';
                $infoHtml = 'Identifiant Steam public (17 chiffres) pour importer votre bibliothèque. '
                    . 'Trouvable sur <a href="https://steamid.io/" target="_blank" rel="noopener">steamid.io</a> '
                    . 'ou dans l’URL de votre profil Steam.';
                $infoAria = 'Aide sur le SteamID64';
                require MONCINE_ROOT . '/templates/_form_label_info.php';
                unset($infoHtml, $infoAria);
                ?>
                <input type="text" name="steam_id" id="account_steam_id" inputmode="numeric" pattern="[0-9]*"
                       autocomplete="off" placeholder="76561198000000000"
                       value="<?= Moncine\View::escape((string) ($user['steam_id'] ?? '')) ?>">
                <?php endif; ?>

                <?php
                unset($info, $infoHtml, $infoAria);
                $for = 'account_email';
                $label = 'E-mail';
                $info = 'Si vous changez l’e-mail, un lien de confirmation sera envoyé à la nouvelle adresse et un message d’information à l’ancienne.';
                $infoAria = 'Aide sur le changement d’e-mail';
                require MONCINE_ROOT . '/templates/_form_label_info.php';
                unset($info, $infoAria);
                ?>
                <input type="email" name="email" id="account_email" required autocomplete="email"
                       value="<?= Moncine\View::escape((string) ($user['email'] ?? '')) ?>">

                <?php
                unset($info, $infoHtml, $infoAria);
                $for = 'profile_password';
                $label = 'Mot de passe actuel';
                $info = 'Obligatoire pour enregistrer le profil, en particulier en cas de changement d’e-mail.';
                $infoAria = 'Aide sur le mot de passe actuel';
                require MONCINE_ROOT . '/templates/_form_label_info.php';
                unset($info, $infoAria);
                ?>
                <input type="password" name="profile_password" id="profile_password" required
                       autocomplete="current-password" maxlength="<?= $maxLen ?>">

                <p class="account-meta">
                    Rôle : <?= Moncine\View::escape(Moncine\UserRole::label((string) ($user['role'] ?? ''))) ?>
                    <?php if ($foyer !== null): ?>
                        · Foyer : <?= Moncine\View::escape((string) ($foyer['nom'] ?? '')) ?>
                    <?php endif; ?>
                </p>

                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>
        </div>
    </details>

    <details class="catalog-admin-panel">
        <summary class="catalog-admin-panel__summary">Mot de passe</summary>
        <div class="catalog-admin-panel__body">
            <form method="post" action="/parametres.php" class="import-form auth-form account-form">
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

    <?php if ($canDeleteAccount): ?>
    <details class="catalog-admin-panel account-page__danger-zone">
        <summary class="catalog-admin-panel__summary catalog-admin-panel__summary--with-info">
            Supprimer mon compte
            <span class="info-tooltip" tabindex="0" aria-label="Conséquences de la suppression du compte">
                <span class="info-tooltip__icon" aria-hidden="true">i</span>
                <span class="info-tooltip__popup" role="tooltip">
                    Action définitive : compte, envies personnelles et historique de vision supprimés.
                    <?php if ($isSoloGroupMember): ?>
                        Vous êtes seul dans votre groupe famille : sa collection partagée sera aussi supprimée.
                    <?php else: ?>
                        Les médias du groupe famille restent pour les autres membres.
                    <?php endif; ?>
                </span>
            </span>
        </summary>
        <div class="catalog-admin-panel__body">
            <form method="post" action="/parametres.php" class="import-form auth-form account-form"
                  onsubmit="return confirm('Supprimer définitivement votre compte ? Cette action est irréversible.');">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="delete_account">

                <label for="delete_current_password">Mot de passe actuel</label>
                <input type="password" name="current_password" id="delete_current_password" required
                       autocomplete="current-password" maxlength="<?= $maxLen ?>">

                <button type="submit" class="btn btn-danger">Supprimer mon compte</button>
            </form>
        </div>
    </details>
    <?php else: ?>
    <details class="catalog-admin-panel">
        <summary class="catalog-admin-panel__summary">Supprimer mon compte</summary>
        <div class="catalog-admin-panel__body">
            <p class="account-meta">
                Les comptes administrateur ne peuvent pas être supprimés depuis cette page.
            </p>
        </div>
    </details>
    <?php endif; ?>

    <details class="catalog-admin-panel">
        <summary class="catalog-admin-panel__summary catalog-admin-panel__summary--with-info">
            Partage visiteur
            <span class="info-tooltip" tabindex="0" aria-label="À quoi sert le partage visiteur">
                <span class="info-tooltip__icon" aria-hidden="true">i</span>
                <span class="info-tooltip__popup" role="tooltip">
                    Créez un lien lecture seule pour montrer votre collection ou vos envies sans donner accès à votre compte.
                </span>
            </span>
        </summary>
        <div class="catalog-admin-panel__body">
            <p>
                <a href="/gerer-partages.php" class="btn btn-secondary">Gérer les liens de partage</a>
            </p>
        </div>
    </details>

    <p class="collection-page__footer-links">
        <a href="/mes-amis.php">Mes amis</a>
        ·
        <a href="/mes-groupes.php">Mon groupe famille</a>
        ·
        <a href="/rechercher-utilisateurs.php">Rechercher des utilisateurs</a>
        ·
        <a href="/">← Accueil</a>
    </p>
</section>
