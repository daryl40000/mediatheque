<?php
/**
 * Barre de recherche globale dans l’en-tête.
 *
 * @var string $globalSearchQuery
 */
$globalSearchQuery = trim((string) ($globalSearchQuery ?? ''));
if ((int) ($currentUserId ?? 0) <= 0) {
    return;
}
?>
<div class="site-header__search global-search" id="global-search" data-global-search
     data-search-api="<?= Moncine\View::escape(Moncine\View::globalSearchApiUrl()) ?>">
    <form method="get" action="/recherche.php" class="global-search__form" role="search">
        <label class="visually-hidden" for="global-search-input">Rechercher dans la bibliothèque et le catalogue</label>
        <input type="search" id="global-search-input" name="q" class="global-search__input"
               value="<?= Moncine\View::escape($globalSearchQuery) ?>"
               placeholder="Rechercher…" autocomplete="off" maxlength="120"
               aria-controls="global-search-suggestions" aria-expanded="false" aria-autocomplete="list">
        <button type="submit" class="global-search__submit" aria-label="Lancer la recherche">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 5 1.49-1.49-5-5Zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14Z"/>
            </svg>
        </button>
    </form>
    <div id="global-search-suggestions" class="global-search__suggestions" hidden>
        <ul class="global-search__list" role="listbox" aria-label="Suggestions de recherche"></ul>
        <p class="global-search__footer">
            <a href="#" class="global-search__all-link" hidden>Voir tous les résultats</a>
        </p>
    </div>
</div>
