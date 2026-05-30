<?php
/**
 * @var array<string, mixed>|null $group
 * @var list<array<string, mixed>> $members
 * @var list<array<string, mixed>> $friends
 * @var list<array<string, mixed>> $pendingInvites
 * @var bool $hasGroup
 * @var bool $socialAvailable
 * @var string $error
 * @var string $success
 */
?>
<section class="account-page social-page">
    <h1>Mon groupe famille</h1>
    <p class="lead">
        Le groupe famille partage la <strong>collection de films</strong> (Mes films).
        Chacun garde ses <strong>envies</strong> et son <strong>historique</strong> pour lui.
    </p>

    <?php if ($success !== ''): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <?php if (!$socialAvailable): ?>
        <p class="hint">Les groupes famille ne sont pas encore activés (migration en attente).</p>
    <?php else: ?>

        <?php if ($pendingInvites !== []): ?>
            <h2>Invitations reçues</h2>
            <?php foreach ($pendingInvites as $inv): ?>
                <?php $invId = (int) ($inv['id'] ?? 0); ?>
                <div class="catalog-admin-panel">
                    <p>
                        <strong><?= Moncine\View::escape(Moncine\UserProfile::displayName([
                            'nom' => (string) ($inv['inviter_nom'] ?? ''),
                            'prenom' => (string) ($inv['inviter_prenom'] ?? ''),
                            'pseudo' => (string) ($inv['inviter_pseudo'] ?? ''),
                        ])) ?></strong>
                        vous invite à rejoindre
                        <strong><?= Moncine\View::escape((string) ($inv['foyer_nom'] ?? '')) ?></strong>.
                    </p>
                    <form method="post" action="/mes-groupes.php" class="inline-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="action" value="accept_invite">
                        <input type="hidden" name="invitation_id" value="<?= $invId ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Accepter</button>
                    </form>
                    <form method="post" action="/mes-groupes.php" class="inline-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="action" value="decline_invite">
                        <input type="hidden" name="invitation_id" value="<?= $invId ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Refuser</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($group === null && !$hasGroup): ?>
            <details class="catalog-admin-panel" open>
                <summary class="catalog-admin-panel__summary">Créer un groupe famille</summary>
                <div class="catalog-admin-panel__body">
                    <p class="hint">
                        Un seul groupe famille par personne. Vous pourrez ensuite inviter vos amis.
                    </p>
                    <form method="post" action="/mes-groupes.php" class="import-form auth-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="action" value="create">
                        <label for="group_nom">Nom du groupe</label>
                        <input type="text" name="nom" id="group_nom" required placeholder="Ex. Famille Martin">
                        <button type="submit" class="btn btn-primary">Créer le groupe</button>
                    </form>
                </div>
            </details>
            <p class="hint">Sans groupe, vous ne partagez pas de collection avec d’autres comptes.</p>
        <?php else: ?>
            <?php $foyerId = (int) ($group['id'] ?? 0); ?>
            <h2><?= Moncine\View::escape((string) ($group['nom'] ?? 'Groupe')) ?></h2>
            <p class="hint">
                Votre rôle :
                <?= (string) ($group['role'] ?? '') === 'founder' ? 'fondateur' : 'membre' ?>
                — collection partagée avec <?= count($members) ?> membre(s).
            </p>

            <h3>Membres</h3>
            <ul class="user-search-results">
                <?php foreach ($members as $member):
                    $memberId = (int) ($member['id'] ?? 0);
                    ?>
                    <li class="user-search-results__item">
                        <span class="user-search-results__name">
                            <?php if ($memberId > 0): ?>
                                <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($memberId)) ?>"
                                   class="user-profile-link">
                                    <?= Moncine\View::escape(Moncine\UserProfile::displayName($member)) ?>
                                </a>
                            <?php else: ?>
                                <?= Moncine\View::escape(Moncine\UserProfile::displayName($member)) ?>
                            <?php endif; ?>
                        </span>
                        <span class="user-search-results__meta">
                            <?= (string) ($member['group_role'] ?? '') === 'founder' ? 'Fondateur' : 'Membre' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($friends !== []): ?>
                <details class="catalog-admin-panel">
                    <summary class="catalog-admin-panel__summary">Inviter un ami dans le groupe</summary>
                    <div class="catalog-admin-panel__body">
                        <form method="post" action="/mes-groupes.php" class="import-form auth-form">
                            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                            <input type="hidden" name="action" value="invite">
                            <input type="hidden" name="foyer_id" value="<?= $foyerId ?>">
                            <label for="invitee_id">Choisir un ami</label>
                            <select name="invitee_id" id="invitee_id" required>
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($friends as $friend): ?>
                                    <?php $fid = (int) ($friend['id'] ?? 0); ?>
                                    <option value="<?= $fid ?>">
                                        <?= Moncine\View::escape(Moncine\UserProfile::displayName($friend)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Envoyer l’invitation</button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <p class="hint">Ajoutez des amis pour pouvoir les inviter dans le groupe.</p>
            <?php endif; ?>

            <form method="post" action="/mes-groupes.php" class="inline-form"
                  onsubmit="return confirm('Quitter ce groupe ? Vous ne verrez plus la collection partagée.');">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="leave">
                <button type="submit" class="btn btn-danger-text">Quitter le groupe</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <p class="collection-page__footer-links">
        <a href="/mes-amis.php">Mes amis</a>
        ·
        <a href="/">← Accueil</a>
    </p>
</section>
