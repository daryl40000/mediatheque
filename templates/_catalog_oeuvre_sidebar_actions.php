<?php
/**
 * Boutons rapides fiche catalogue : envies (cœur) et ajout à la collection.
 *
 * @var int $oeuvreId
 * @var string $catalogSearch
 * @var string $catalogSort
 * @var string $catalogDir
 * @var int $catalogPage
 * @var int $profileUserId
 */
$profileUserId = (int) ($profileUserId ?? 0);
?>
<div class="game-detail-sidebar__actions">
    <form method="post" action="/ajouter-oeuvre-bibliotheque.php" class="inline-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="oeuvre_id" value="<?= (int) $oeuvreId ?>">
        <input type="hidden" name="statut" value="<?= Moncine\LibraryStatut::WISHLIST ?>">
        <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape((string) ($catalogSearch ?? '')) ?>">
        <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape((string) ($catalogSort ?? 'titre')) ?>">
        <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape((string) ($catalogDir ?? 'asc')) ?>">
        <input type="hidden" name="catalog_page" value="<?= max(1, (int) ($catalogPage ?? 1)) ?>">
        <?php if ($profileUserId > 0): ?>
            <input type="hidden" name="profile_user" value="<?= $profileUserId ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-icon btn-secondary btn-sm" title="Ajouter aux envies" aria-label="Ajouter aux envies">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 21s-7-4.35-10-9.5C-0.2 6.9 2.2 3.5 6 3.5c2 0 3.2 1 4 2 0.8-1 2-2 4-2 3.8 0 6.2 3.4 4 8C19 16.65 12 21 12 21z"/>
            </svg>
        </button>
    </form>
    <form method="post" action="/ajouter-oeuvre-bibliotheque.php" class="inline-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="oeuvre_id" value="<?= (int) $oeuvreId ?>">
        <input type="hidden" name="statut" value="<?= Moncine\LibraryStatut::COLLECTION ?>">
        <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape((string) ($catalogSearch ?? '')) ?>">
        <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape((string) ($catalogSort ?? 'titre')) ?>">
        <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape((string) ($catalogDir ?? 'asc')) ?>">
        <input type="hidden" name="catalog_page" value="<?= max(1, (int) ($catalogPage ?? 1)) ?>">
        <?php if ($profileUserId > 0): ?>
            <input type="hidden" name="profile_user" value="<?= $profileUserId ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-icon btn-secondary btn-sm" title="Ajouter à la bibliothèque" aria-label="Ajouter à la bibliothèque">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M19 11H13V5h-2v6H5v2h6v6h2v-6h6z"/>
            </svg>
        </button>
    </form>
</div>
