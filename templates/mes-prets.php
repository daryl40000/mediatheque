<?php
/**
 * @var string $error
 * @var string $success
 * @var list<array<string, mixed>> $pendingRequests
 * @var list<array<string, mixed>> $reservedRequests
 * @var list<array<string, mixed>> $activeLoans
 */
$pendingRequests = $pendingRequests ?? [];
$reservedRequests = $reservedRequests ?? [];
$activeLoans = $activeLoans ?? [];
?>

<section class="account-page">
    <h1>Mes prêts</h1>
    <p class="lead">
        Vos amis peuvent demander un prêt de films ou de jeux physiques. Vous pouvez accepter pour
        <strong>réserver</strong> un exemplaire, puis valider le prêt le jour J.
    </p>

    <?php if ($success !== ''): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <h2>Demandes reçues</h2>
    <?php if ($pendingRequests === []): ?>
        <p class="hint">Aucune demande en attente.</p>
    <?php else: ?>
        <ul class="user-search-results">
            <?php foreach ($pendingRequests as $row): ?>
                <?php
                $requestId = (int) ($row['request_id'] ?? 0);
                $requester = [
                    'id' => (int) ($row['requester_id'] ?? 0),
                    'nom' => (string) ($row['requester_nom'] ?? ''),
                    'prenom' => (string) ($row['requester_prenom'] ?? ''),
                    'pseudo' => (string) ($row['requester_pseudo'] ?? ''),
                ];
                ?>
                <li class="user-search-results__item">
                    <?php require MONCINE_ROOT . '/templates/_loan_list_item_title.php'; ?>
                    <span class="user-search-results__meta">
                        demandé par
                        <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl((int) $requester['id'])) ?>">
                            <?= Moncine\View::escape(Moncine\UserProfile::displayName($requester)) ?>
                        </a>
                    </span>
                    <form method="post" action="/mes-prets.php" class="inline-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="action" value="accept">
                        <input type="hidden" name="request_id" value="<?= $requestId ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Accepter (réserver)</button>
                    </form>
                    <form method="post" action="/mes-prets.php" class="inline-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="action" value="decline">
                        <input type="hidden" name="request_id" value="<?= $requestId ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Refuser</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Exemplaires réservés (à prêter)</h2>
    <?php if ($reservedRequests === []): ?>
        <p class="hint">Aucun exemplaire réservé.</p>
    <?php else: ?>
        <ul class="user-search-results">
            <?php foreach ($reservedRequests as $row): ?>
                <?php
                $requestId = (int) ($row['request_id'] ?? 0);
                $requester = [
                    'id' => (int) ($row['requester_id'] ?? 0),
                    'nom' => (string) ($row['requester_nom'] ?? ''),
                    'prenom' => (string) ($row['requester_prenom'] ?? ''),
                    'pseudo' => (string) ($row['requester_pseudo'] ?? ''),
                ];
                ?>
                <li class="user-search-results__item">
                    <?php require MONCINE_ROOT . '/templates/_loan_list_item_title.php'; ?>
                    <span class="user-search-results__meta">
                        réservé pour
                        <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl((int) $requester['id'])) ?>">
                            <?= Moncine\View::escape(Moncine\UserProfile::displayName($requester)) ?>
                        </a>
                    </span>
                    <form method="post" action="/mes-prets.php" class="inline-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="action" value="lend">
                        <input type="hidden" name="request_id" value="<?= $requestId ?>">
                        <label class="hint" style="margin-right:.5rem">
                            Retour prévu (optionnel)
                            <input type="date" name="due_at">
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm">Valider le prêt</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Prêts en cours</h2>
    <?php if ($activeLoans === []): ?>
        <p class="hint">Aucun prêt en cours.</p>
    <?php else: ?>
        <ul class="user-search-results">
            <?php foreach ($activeLoans as $row): ?>
                <?php
                $loanId = (int) ($row['loan_id'] ?? 0);
                $borrower = [
                    'id' => (int) ($row['borrower_id'] ?? 0),
                    'nom' => (string) ($row['borrower_nom'] ?? ''),
                    'prenom' => (string) ($row['borrower_prenom'] ?? ''),
                    'pseudo' => (string) ($row['borrower_pseudo'] ?? ''),
                ];
                ?>
                <li class="user-search-results__item">
                    <?php require MONCINE_ROOT . '/templates/_loan_list_item_title.php'; ?>
                    <span class="user-search-results__meta">
                        prêté à
                        <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl((int) $borrower['id'])) ?>">
                            <?= Moncine\View::escape(Moncine\UserProfile::displayName($borrower)) ?>
                        </a>
                    </span>
                    <form method="post" action="/mes-prets.php" class="inline-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="action" value="return">
                        <input type="hidden" name="loan_id" value="<?= $loanId ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Marquer rendu</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p class="collection-page__footer-links">
        <a href="/mes-amis.php">← Mes amis</a>
        ·
        <a href="/">← Accueil</a>
    </p>
</section>

