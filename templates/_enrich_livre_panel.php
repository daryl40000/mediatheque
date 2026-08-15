<?php
/**
 * Enrichissement Open Library (fiche livre catalogue ou bibliothèque).
 *
 * @var string $enrichTarget oeuvre|book
 * @var int $entityId
 * @var bool $canEnrichOpenLibrary
 * @var string|null $enrichStatus ok|not_found|error
 * @var string $enrichMessage
 * @var string $currentOpenLibraryId
 * @var string $currentIsbn
 * @var string $currentPosterUrl
 */
$enrichTarget = $enrichTarget ?? 'oeuvre';
$entityId = (int) ($entityId ?? 0);
$enrichMessage = $enrichMessage ?? '';
$currentOpenLibraryId = trim((string) ($currentOpenLibraryId ?? ''));
$currentIsbn = trim((string) ($currentIsbn ?? ''));
$currentPosterUrl = trim((string) ($currentPosterUrl ?? ''));
$hasExistingPoster = $currentPosterUrl !== '';
$canEnrichOpenLibrary = !empty($canEnrichOpenLibrary);

$formAction = $enrichTarget === 'book' ? '/enrichir-livre.php' : '/enrichir-oeuvre-livre.php';
$idFieldName = $enrichTarget === 'book' ? 'book_id' : 'oeuvre_id';
$panelTitle = $enrichTarget === 'book'
    ? 'Enrichir cette fiche'
    : 'Enrichir cette fiche livre';
$olPublicUrl = $currentOpenLibraryId !== ''
    ? Moncine\OpenLibraryClient::publicEditionUrl($currentOpenLibraryId)
    : '';
?>
<div class="enrich-film-panel enrich-livre-panel">
    <h2 class="enrich-film-panel__title"><?= Moncine\View::escape($panelTitle) ?></h2>
    <?php if ($canEnrichOpenLibrary): ?>
        <p class="hint">
            <strong>Open Library</strong> complète la fiche : couverture, auteur, ISBN, éditeur, pages, année,
            résumé, sous-titre, saga et n° dans la saga.
            Priorité à l’ISBN s’il est déjà renseigné, sinon recherche par titre.
            <?php if ($hasExistingPoster): ?>
                Si une couverture est déjà présente, cochez <strong>Garder la couverture</strong> pour ne pas la remplacer.
            <?php endif; ?>
            <?php if ($enrichTarget === 'book'): ?>
                La fiche catalogue partagée en profitera aussi.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($enrichMessage)): ?>
        <p class="alert <?= ($enrichStatus ?? '') === 'ok' ? 'alert-success' : 'alert-warning' ?>">
            <?= Moncine\View::escape($enrichMessage) ?>
        </p>
    <?php endif; ?>

    <?php if (!$canEnrichOpenLibrary): ?>
        <p class="hint">
            Enrichissement Open Library indisponible (appliquez les migrations, ou rechargez la page).
        </p>
    <?php else: ?>
        <form method="post" action="<?= Moncine\View::escape($formAction) ?>" class="inline-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="<?= Moncine\View::escape($idFieldName) ?>" value="<?= $entityId ?>">
            <input type="hidden" name="action" value="enrich">
            <div class="export-actions">
                <button type="submit" class="btn btn-accent">Enrichir (ISBN ou titre)</button>
                <?php if ($hasExistingPoster): ?>
                    <label class="checkbox enrich-game-panel__keep-poster">
                        <input type="checkbox" name="keep_poster" value="1" checked>
                        Garder la couverture
                    </label>
                <?php endif; ?>
            </div>
        </form>

        <form method="post" action="<?= Moncine\View::escape($formAction) ?>" class="inline-form enrich-film-panel__correct">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="<?= Moncine\View::escape($idFieldName) ?>" value="<?= $entityId ?>">
            <input type="hidden" name="action" value="isbn">
            <label for="enrich_livre_isbn">Enrichir avec un ISBN</label>
            <input type="text" name="isbn" id="enrich_livre_isbn" value="<?= Moncine\View::escape($currentIsbn) ?>"
                   placeholder="978…" inputmode="numeric" autocomplete="off">
            <div class="export-actions">
                <button type="submit" class="btn btn-secondary">Chercher cet ISBN</button>
                <?php if ($hasExistingPoster): ?>
                    <label class="checkbox enrich-game-panel__keep-poster">
                        <input type="checkbox" name="keep_poster" value="1" checked>
                        Garder la couverture
                    </label>
                <?php endif; ?>
            </div>
        </form>

        <form method="post" action="<?= Moncine\View::escape($formAction) ?>" class="inline-form enrich-film-panel__correct">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="<?= Moncine\View::escape($idFieldName) ?>" value="<?= $entityId ?>">
            <input type="hidden" name="action" value="openlibrary">
            <label for="enrich_openlibrary_id">
                ID édition Open Library
                <?php if ($olPublicUrl !== '' && $currentOpenLibraryId !== ''): ?>
                    — <a href="<?= Moncine\View::escape($olPublicUrl) ?>" target="_blank" rel="noopener">
                        voir <?= Moncine\View::escape($currentOpenLibraryId) ?>
                    </a>
                <?php endif; ?>
            </label>
            <input type="text" name="openlibrary_id" id="enrich_openlibrary_id"
                   value="<?= Moncine\View::escape($currentOpenLibraryId) ?>"
                   placeholder="OL123456M ou URL openlibrary.org/books/…">
            <div class="export-actions">
                <button type="submit" class="btn btn-secondary">Appliquer cet ID</button>
                <?php if ($hasExistingPoster): ?>
                    <label class="checkbox enrich-game-panel__keep-poster">
                        <input type="checkbox" name="keep_poster" value="1" checked>
                        Garder la couverture
                    </label>
                <?php endif; ?>
            </div>
        </form>
        <p class="hint">
            Open Library est un catalogue libre (Internet Archive) —
            <a href="https://openlibrary.org/" target="_blank" rel="noopener">openlibrary.org</a>.
            Aucune clé API n’est nécessaire.
        </p>
    <?php endif; ?>
</div>
