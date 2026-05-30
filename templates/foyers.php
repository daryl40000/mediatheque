<?php
/**
 * @var list<array<string, mixed>> $foyers
 * @var list<array<string, mixed>> $users
 * @var bool $readOnly
 * @var string $error
 * @var string $success
 */
$readOnly = $readOnly ?? true;
?>
<section class="users-admin-page">
    <h1>Groupes famille</h1>
    <p class="lead">
        Consultation des groupes famille existants.
        Depuis la phase 6, les utilisateurs créent et gèrent leurs groupes via
        <a href="/mes-groupes.php">Mon groupe famille</a> — l’administrateur ne crée plus de foyer ici.
    </p>

    <?php if ($success !== ''): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <h2>Groupes existants</h2>
    <?php if ($foyers === []): ?>
        <p class="hint">Aucun groupe pour l’instant.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="films-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Membres</th>
                        <th>Films en collection</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($foyers as $foyer): ?>
                        <tr>
                            <td><?= Moncine\View::escape((string) ($foyer['nom'] ?? '')) ?></td>
                            <td><?= (int) ($foyer['member_count'] ?? 0) ?></td>
                            <td><?= (int) ($foyer['collection_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2>Comptes et groupe actif</h2>
    <div class="table-scroll">
        <table class="films-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>E-mail</th>
                    <th>Groupe (foyer_id)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= Moncine\View::escape((string) ($user['nom'] ?? '')) ?></td>
                        <td><?= Moncine\View::escape((string) ($user['email'] ?? '')) ?></td>
                        <td>
                            <?php if (!empty($user['foyer_nom'])): ?>
                                <?= Moncine\View::escape((string) $user['foyer_nom']) ?>
                            <?php else: ?>
                                <span class="hint">Aucun</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="collection-page__footer-links">
        <a href="/utilisateurs.php">Comptes utilisateurs</a> · <a href="/">Accueil</a>
    </p>
</section>
