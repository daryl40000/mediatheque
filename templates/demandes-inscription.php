<?php
/**
 * @var list<array<string, mixed>> $pending
 * @var array<string, mixed>|null $review
 * @var int $pendingCount
 * @var string $registrationMode
 * @var bool $approved
 * @var bool $rejected
 * @var string $saveError
 */

use Moncine\RegistrationSettings;
use Moncine\View;

$labels = RegistrationSettings::modeLabels();
?>
<section class="users-admin-page">
    <h1>Inscriptions à valider</h1>
    <p class="lead">
        Mode actuel :
        <strong><?= View::escape($labels[$registrationMode] ?? $registrationMode) ?></strong>
        — <a href="/utilisateurs.php">Modifier dans Comptes utilisateurs</a>
    </p>

    <?php if ($approved): ?>
        <p class="alert alert-success">Compte créé. L’utilisateur a été notifié par e-mail s’il est configuré.</p>
    <?php endif; ?>
    <?php if ($rejected): ?>
        <p class="alert alert-success">Demande refusée.</p>
    <?php endif; ?>
    <?php if ($saveError !== ''): ?>
        <p class="alert alert-warning"><?= View::escape($saveError) ?></p>
    <?php endif; ?>

    <?php if ($review !== null):
        $rid = (int) ($review['id'] ?? 0);
        ?>
        <article class="catalog-admin-panel">
            <h2>Demande #<?= $rid ?></h2>
            <dl class="film-meta">
                <dt>E-mail</dt>
                <dd><?= View::escape((string) ($review['email'] ?? '')) ?></dd>
                <dt>Nom</dt>
                <dd><?= View::escape(trim((string) ($review['prenom'] ?? '') . ' ' . (string) ($review['nom'] ?? ''))) ?></dd>
                <?php if (trim((string) ($review['pseudo'] ?? '')) !== ''): ?>
                    <dt>Pseudo</dt>
                    <dd><?= View::escape((string) ($review['pseudo'] ?? '')) ?></dd>
                <?php endif; ?>
                <dt>E-mail confirmé le</dt>
                <dd><?= View::escape((string) ($review['email_confirmed_at'] ?? '')) ?></dd>
            </dl>

            <form method="post" action="/traiter-inscription.php" class="import-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="request_id" value="<?= $rid ?>">
                <label for="review_note">Message (optionnel, envoyé en cas de refus)</label>
                <textarea name="review_note" id="review_note" rows="2"></textarea>
                <div class="form-actions form-actions--split">
                    <button type="submit" name="action" value="approve" class="btn btn-primary">Approuver et créer le compte</button>
                    <button type="submit" name="action" value="reject" class="btn btn-secondary">Refuser</button>
                </div>
            </form>
            <p class="hint"><a href="/demandes-inscription.php">Retour à la liste</a></p>
        </article>
    <?php elseif ($pending === []): ?>
        <p>Aucune inscription en attente<?= $pendingCount > 0 ? '' : '.' ?></p>
    <?php else: ?>
        <p><?= (int) count($pending) ?> demande(s) en attente.</p>
        <ul class="share-link-list">
            <?php foreach ($pending as $row):
                $id = (int) ($row['id'] ?? 0);
                $label = trim((string) ($row['prenom'] ?? '') . ' ' . (string) ($row['nom'] ?? ''));
                if ($label === '') {
                    $label = (string) ($row['email'] ?? '');
                }
                ?>
                <li class="share-link-list__item">
                    <a href="/demandes-inscription.php?id=<?= $id ?>"><?= View::escape($label) ?></a>
                    — <?= View::escape((string) ($row['email'] ?? '')) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
