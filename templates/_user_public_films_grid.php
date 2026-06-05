<?php
/**
 * Grille lecture seule des films d’un autre utilisateur.
 *
 * @var list<array<string, mixed>> $films
 * @var int $targetUserId
 * @var string $listMode
 * @var string $sortBy
 * @var string $sortDir
 * @var int $viewerId
 * @var bool $areFriends
 * @var string $profileDomain
 * @var array{
 *   activeLoans?: array<int, bool>,
 *   myRequests?: array<int, array{request_id:int, status:string}>,
 *   reservedByOthers?: array<int, bool>
 * } $loanUi
 */
$sortLink = static function (string $label, string $column) use ($targetUserId, $listMode, $sortBy, $sortDir, $profileDomain): void {
    $active = $sortBy === $column;
    $domain = $profileDomain ?? Moncine\MediaDomain::FILM;
    ?>
    <a href="<?= Moncine\View::escape(
        Moncine\View::userProfileListUrl($targetUserId, $listMode, $column, $sortBy, $sortDir, null, $domain)
    ) ?>"
       class="collection-grid-sort__link<?= $active ? ' is-active' : '' ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
?>
<?php if ($films === []): ?>
    <p class="hint">Aucun film dans cette liste.</p>
<?php else: ?>
    <p class="stats"><?= count($films) ?> film<?= count($films) > 1 ? 's' : '' ?></p>
    <nav class="collection-grid-sort social-profile-list-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $sortLink('Titre', 'titre'); ?>
        <?php $sortLink('Année', 'annee'); ?>
        <?php $sortLink('Réalisateur', 'realisateur'); ?>
    </nav>
    <ul class="collection-grid social-profile-grid" role="list">
        <?php foreach ($films as $film):
            $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null);
            $titre = (string) ($film['titre'] ?? '');
            $annee = (int) ($film['annee'] ?? 0);
            $bibliothequeId = (int) ($film['id'] ?? 0);
            $ownerUserId = (int) ($film['user_id'] ?? 0);
            $canRequestLoan = ($listMode ?? '') === 'collection'
                && !empty($areFriends)
                && (int) ($viewerId ?? 0) > 0
                && (int) ($viewerId ?? 0) !== $targetUserId
                && $ownerUserId === (int) $targetUserId
                && $bibliothequeId > 0;

            // Conserve exactement l'URL actuelle (tri, direction, etc.) après une demande de prêt,
            // sans déclencher la logique de bascule de View::userProfileListUrl().
            $returnTo = '/utilisateur.php?' . http_build_query([
                'id' => (string) $targetUserId,
                'liste' => (string) ($listMode ?? 'collection'),
                'sort' => (string) ($sortBy ?? 'titre'),
                'dir' => (string) ($sortDir ?? 'asc'),
                'domain' => (string) ($profileDomain ?? Moncine\MediaDomain::FILM),
            ], '', '&', PHP_QUERY_RFC3986);

            $activeLoans = $loanUi['activeLoans'] ?? [];
            $myRequests = $loanUi['myRequests'] ?? [];
            $reservedByOthers = $loanUi['reservedByOthers'] ?? [];

            $isLoaned = !empty($activeLoans[$bibliothequeId]);
            $myReq = $myRequests[$bibliothequeId] ?? null;
            $isReservedByOther = !empty($reservedByOthers[$bibliothequeId]);
            ?>
            <li class="collection-grid__item" role="listitem">
                <article class="collection-grid__card">
                    <div class="collection-grid__link social-profile-grid__card">
                        <?php if ($posterSrc !== ''): ?>
                            <div class="collection-grid__poster-wrap">
                                <img class="collection-grid__poster" src="<?= $posterSrc ?>"
                                     alt="Affiche de <?= Moncine\View::escape($titre) ?>"
                                     loading="lazy" decoding="async">
                            </div>
                        <?php else: ?>
                            <div class="collection-grid__poster-wrap collection-grid__poster-wrap--empty">
                                <span class="collection-grid__poster-placeholder">?</span>
                            </div>
                        <?php endif; ?>
                        <div class="collection-grid__meta">
                            <span class="collection-grid__title"><?= Moncine\View::escape($titre) ?></span>
                            <?php if ($annee > 0): ?>
                                <span class="collection-grid__year"><?= $annee ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (($listMode ?? '') === 'collection' && !empty($areFriends)): ?>
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
                            <p class="hint">Ce film est déjà prêté.</p>
                        <?php elseif (is_array($myReq) && (int) ($myReq['request_id'] ?? 0) > 0): ?>
                            <form method="post" action="/annuler-demande-pret.php" class="inline-form" style="margin-top:.35rem">
                                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                <input type="hidden" name="request_id" value="<?= (int) $myReq['request_id'] ?>">
                                <input type="hidden" name="return_to" value="<?= Moncine\View::escape($returnTo) ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Annuler ma demande</button>
                            </form>
                        <?php elseif ($isReservedByOther): ?>
                            <p class="hint">Ce film est déjà réservé.</p>
                        <?php else: ?>
                            <form method="post" action="/demander-pret.php" class="inline-form" style="margin-top:.35rem">
                                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                <input type="hidden" name="bibliotheque_id" value="<?= $bibliothequeId ?>">
                                <input type="hidden" name="owner_user_id" value="<?= (int) $targetUserId ?>">
                                <input type="hidden" name="return_to" value="<?= Moncine\View::escape($returnTo) ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Demander un prêt</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
