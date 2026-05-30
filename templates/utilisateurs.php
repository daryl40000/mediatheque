<?php
/**
 * @var list<array<string, mixed>> $users
 * @var list<array<string, mixed>> $foyers
 * @var string $error
 * @var string $success
 * @var int $currentUserId
 * @var \Moncine\RegistrationSettings|null $registrationSettings
 * @var int $pendingRegistrations
 */

use Moncine\RegistrationSettings;
use Moncine\RegistrationService;
?>
<section class="users-admin-page">
    <h1>Comptes utilisateurs</h1>
    <p class="lead">
        Les groupes famille sont gérés par les utilisateurs (<strong>Mes amis</strong>, <strong>Mon groupe famille</strong>).
        Consultation des groupes : <a href="/foyers.php">Groupes famille</a>.
        <?php if (RegistrationService::isAvailable() && $pendingRegistrations > 0): ?>
            — <a href="/demandes-inscription.php">Inscriptions à valider (<?= (int) $pendingRegistrations ?>)</a>
        <?php endif; ?>
    </p>

    <?php if ($registrationSettings !== null): ?>
        <details class="catalog-admin-panel">
            <summary class="catalog-admin-panel__summary">Inscription publique</summary>
            <div class="catalog-admin-panel__body">
                <form method="post" action="/utilisateurs.php" class="import-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="set_registration_mode">
                    <label for="registration_mode">Qui peut créer un compte ?</label>
                    <select name="registration_mode" id="registration_mode">
                        <?php
                        $currentMode = $registrationSettings->getMode();
                        foreach (RegistrationSettings::modeLabels() as $value => $label):
                            ?>
                            <option value="<?= Moncine\View::escape($value) ?>"<?= $currentMode === $value ? ' selected' : '' ?>>
                                <?= Moncine\View::escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint">
                        Dans tous les cas, l’utilisateur doit confirmer son e-mail.
                        Une seule demande active par adresse e-mail.
                    </p>
                    <button type="submit" class="btn btn-primary">Enregistrer le réglage</button>
                </form>
                <?php if ($pendingRegistrations > 0): ?>
                    <p><a href="/demandes-inscription.php" class="btn btn-secondary">
                        Voir les demandes en attente (<?= (int) $pendingRegistrations ?>)
                    </a></p>
                <?php endif; ?>
            </div>
        </details>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <details class="catalog-admin-panel" open>
        <summary class="catalog-admin-panel__summary">Ajouter un compte</summary>
        <div class="catalog-admin-panel__body">
            <form method="post" action="/utilisateurs.php" class="import-form auth-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="create">
                <label for="new_prenom">Prénom</label>
                <input type="text" name="prenom" id="new_prenom" autocomplete="given-name">
                <label for="new_nom">Nom</label>
                <input type="text" name="nom" id="new_nom" autocomplete="family-name">
                <label for="new_pseudo">Pseudo</label>
                <input type="text" name="pseudo" id="new_pseudo" autocomplete="nickname"
                       maxlength="<?= Moncine\UserProfile::MAX_PSEUDO_LENGTH ?>"
                       placeholder="Optionnel">
                <label for="new_email">E-mail</label>
                <input type="email" name="email" id="new_email" required>
                <label for="new_password">Mot de passe provisoire</label>
                <input type="password" name="password" id="new_password" required
                       minlength="<?= Moncine\UtilisateurRepository::MIN_PASSWORD_LENGTH ?>"
                       maxlength="<?= Moncine\UtilisateurRepository::MAX_PASSWORD_LENGTH ?>"
                       autocomplete="new-password">
                <label for="new_role">Rôle</label>
                <select name="role" id="new_role">
                    <option value="<?= Moncine\View::escape(Moncine\UserRole::USER) ?>">
                        <?= Moncine\View::escape(Moncine\UserRole::label(Moncine\UserRole::USER)) ?>
                    </option>
                    <option value="<?= Moncine\View::escape(Moncine\UserRole::ADMIN) ?>">
                        <?= Moncine\View::escape(Moncine\UserRole::label(Moncine\UserRole::ADMIN)) ?>
                    </option>
                </select>
                <button type="submit" class="btn btn-primary">Créer le compte</button>
            </form>
        </div>
    </details>

    <h2>Comptes existants</h2>
    <div class="table-scroll">
        <table class="films-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>E-mail</th>
                    <th>Foyer</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user):
                    $uid = (int) ($user['id'] ?? 0);
                    $active = (int) ($user['actif'] ?? 0) === 1;
                    $userFoyerId = (int) ($user['foyer_id'] ?? 0);
                    ?>
                    <tr>
                        <td><?= Moncine\View::escape(Moncine\View::userDisplayName($user)) ?></td>
                        <td><?= Moncine\View::escape((string) ($user['email'] ?? '')) ?></td>
                        <td>
                            <?php if (!empty($user['foyer_nom'])): ?>
                                <?= Moncine\View::escape((string) $user['foyer_nom']) ?>
                            <?php else: ?>
                                <span class="hint">Aucun</span>
                            <?php endif; ?>
                        </td>
                        <td><?= Moncine\View::escape(Moncine\UserRole::label((string) ($user['role'] ?? ''))) ?></td>
                        <td><?= $active ? 'Actif' : 'Désactivé' ?></td>
                        <td class="users-admin-page__actions">
                            <?php if ($uid !== $currentUserId): ?>
                                <form method="post" action="/utilisateurs.php" class="inline-form users-admin-page__action-form">
                                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                    <input type="hidden" name="actif" value="<?= $active ? '0' : '1' ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <?= $active ? 'Désactiver' : 'Réactiver' ?>
                                    </button>
                                </form>
                                <form method="post" action="/utilisateurs.php" class="inline-form users-admin-page__action-form"
                                      onsubmit="return confirm('Générer un mot de passe provisoire ?');">
                                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Réinit. MDP</button>
                                </form>
                                <form method="post" action="/utilisateurs.php" class="inline-form users-admin-page__action-form"
                                      onsubmit="return confirm('Supprimer ce compte ?');">
                                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                                    <button type="submit" class="btn btn-danger-text btn-sm">Supprimer</button>
                                </form>
                            <?php else: ?>
                                <span class="hint">Vous</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="collection-page__footer-links">
        <a href="/foyers.php">Groupes famille (lecture seule)</a> · <a href="/">Accueil</a>
    </p>
</section>
