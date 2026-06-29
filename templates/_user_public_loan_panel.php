<?php
/**
 * Boutons et statut de prêt sur une grille de profil public (films ou jeux).
 *
 * @var int $bibliothequeId
 * @var int $targetUserId
 * @var int $ownerUserId
 * @var int $viewerId
 * @var bool $areFriends
 * @var string $listMode
 * @var array{
 *   activeLoans?: array<int, bool>,
 *   myRequests?: array<int, array{request_id:int, status:string}>,
 *   reservedByOthers?: array<int, bool>
 * } $loanUi
 * @var string $returnTo
 * @var bool $isLoanable
 * @var string $mediaItemLabel libellé court : « film » ou « jeu »
 */
$bibliothequeId = (int) ($bibliothequeId ?? 0);
$targetUserId = (int) ($targetUserId ?? 0);
$ownerUserId = (int) ($ownerUserId ?? 0);
$viewerId = (int) ($viewerId ?? 0);
$areFriends = !empty($areFriends);
$listMode = (string) ($listMode ?? '');
$loanUi = $loanUi ?? [];
$returnTo = (string) ($returnTo ?? '');
$isLoanable = !empty($isLoanable);
$mediaItemLabel = trim((string) ($mediaItemLabel ?? 'film'));
if ($mediaItemLabel === '') {
    $mediaItemLabel = 'film';
}

$canRequestLoan = $listMode === 'collection'
    && $areFriends
    && $viewerId > 0
    && $viewerId !== $targetUserId
    && $ownerUserId === $targetUserId
    && $bibliothequeId > 0
    && $isLoanable;

$activeLoans = $loanUi['activeLoans'] ?? [];
$myRequests = $loanUi['myRequests'] ?? [];
$reservedByOthers = $loanUi['reservedByOthers'] ?? [];

$isLoaned = !empty($activeLoans[$bibliothequeId]);
$myReq = $myRequests[$bibliothequeId] ?? null;
$isReservedByOther = !empty($reservedByOthers[$bibliothequeId]);
?>
<?php if ($listMode === 'collection' && $areFriends): ?>
    <p class="hint" style="margin-top:.35rem;margin-bottom:.35rem">
        <?php if ($isLoaned): ?>
            Statut : <strong>déjà prêté</strong>
        <?php elseif (is_array($myReq) && ($myReq['status'] ?? '') === Moncine\LoanRequestRepository::STATUS_PENDING): ?>
            Statut : <strong>demande envoyée</strong>
        <?php elseif (is_array($myReq) && ($myReq['status'] ?? '') === Moncine\LoanRequestRepository::STATUS_ACCEPTED): ?>
            Statut : <strong>réservé pour vous</strong>
        <?php elseif ($isReservedByOther): ?>
            Statut : <strong>réservé</strong>
        <?php endif; ?>
    </p>
<?php endif; ?>
<?php if ($canRequestLoan): ?>
    <?php if ($isLoaned): ?>
        <p class="hint">Ce <?= Moncine\View::escape($mediaItemLabel) ?> est déjà prêté.</p>
    <?php elseif (is_array($myReq) && (int) ($myReq['request_id'] ?? 0) > 0): ?>
        <form method="post" action="/annuler-demande-pret.php" class="inline-form" style="margin-top:.35rem">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="request_id" value="<?= (int) $myReq['request_id'] ?>">
            <input type="hidden" name="return_to" value="<?= Moncine\View::escape($returnTo) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Annuler ma demande</button>
        </form>
    <?php elseif ($isReservedByOther): ?>
        <p class="hint">Ce <?= Moncine\View::escape($mediaItemLabel) ?> est déjà réservé.</p>
    <?php else: ?>
        <form method="post" action="/demander-pret.php" class="inline-form" style="margin-top:.35rem">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="bibliotheque_id" value="<?= $bibliothequeId ?>">
            <input type="hidden" name="owner_user_id" value="<?= (int) $targetUserId ?>">
            <input type="hidden" name="return_to" value="<?= Moncine\View::escape($returnTo) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Demander un prêt</button>
        </form>
    <?php endif; ?>
<?php elseif ($listMode === 'collection' && $areFriends && !$isLoanable && $viewerId > 0 && $viewerId !== $targetUserId): ?>
    <p class="hint">Non prêtable.</p>
<?php endif; ?>
