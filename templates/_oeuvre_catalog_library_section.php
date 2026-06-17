<?php
/**
 * Section bibliothèque sur une fiche catalogue (jeu, magazine, etc.).
 *
 * @var string $mediaDomain
 * @var int $oeuvreId
 * @var bool $inLibrary
 * @var array<string, mixed>|null $libraryEntry
 * @var string $libraryStatut
 * @var int|null $libraryBibId
 * @var string $collectionLabel
 * @var string $wishlistLabel
 * @var string $openLibraryLabel
 * @var string $catalogSearch
 * @var string $catalogSort
 * @var string $catalogDir
 * @var int $catalogPage
 */
$libraryUrl = match ($mediaDomain) {
    Moncine\MediaDomain::JEU => Moncine\View::gameUrl((int) ($libraryBibId ?? 0)),
    Moncine\MediaDomain::MAGAZINE => Moncine\View::magazineIssueUrl((int) ($libraryBibId ?? 0)),
    default => '/film.php?id=' . (int) ($libraryBibId ?? 0),
};
?>
<section class="oeuvre-catalog-page__library">
    <h2>Votre bibliothèque</h2>
    <?php if ($inLibrary): ?>
        <p class="hint">
            Cette fiche est dans
            <strong><?= Moncine\View::escape(Moncine\LibraryStatut::label($libraryStatut)) ?></strong>.
        </p>
        <p>
            <a href="<?= Moncine\View::escape($libraryUrl) ?>" class="btn btn-primary">
                <?= Moncine\View::escape($openLibraryLabel) ?>
            </a>
        </p>
    <?php else: ?>
        <p class="hint">
            Cette fiche n’est pas encore dans votre collection ni dans vos envies.
        </p>
        <div class="oeuvre-catalog-page__actions">
            <form method="post" action="/ajouter-oeuvre-bibliotheque.php" class="inline-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="oeuvre_id" value="<?= (int) $oeuvreId ?>">
                <input type="hidden" name="statut" value="<?= Moncine\View::escape(Moncine\LibraryStatut::COLLECTION) ?>">
                <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch ?? '') ?>">
                <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort ?? 'titre') ?>">
                <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir ?? 'asc') ?>">
                <input type="hidden" name="catalog_page" value="<?= max(1, (int) ($catalogPage ?? 1)) ?>">
                <button type="submit" class="btn btn-secondary">Ajouter à <?= Moncine\View::escape($collectionLabel) ?></button>
            </form>
            <form method="post" action="/ajouter-oeuvre-bibliotheque.php" class="inline-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="oeuvre_id" value="<?= (int) $oeuvreId ?>">
                <input type="hidden" name="statut" value="<?= Moncine\View::escape(Moncine\LibraryStatut::WISHLIST) ?>">
                <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch ?? '') ?>">
                <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort ?? 'titre') ?>">
                <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir ?? 'asc') ?>">
                <input type="hidden" name="catalog_page" value="<?= max(1, (int) ($catalogPage ?? 1)) ?>">
                <button type="submit" class="btn btn-secondary">Ajouter à <?= Moncine\View::escape($wishlistLabel) ?></button>
            </form>
        </div>
    <?php endif; ?>
</section>
