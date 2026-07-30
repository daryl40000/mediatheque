<?php
/**
 * Liens vers les jeux dont parle le livre (catégorie Jeux vidéo).
 *
 * @var list<array<string, mixed>> $linkedGames
 * @var bool $forceVisible
 */
$linkedGames = $linkedGames ?? [];
$forceVisible = !empty($forceVisible);
$jeuxVideoKey = Moncine\LivreCategory::filterKey(Moncine\LivreCategory::JEUX_VIDEO);
?>
<div class="livre-game-links-field"
     data-livre-game-links
     data-jeux-video-key="<?= Moncine\View::escape($jeuxVideoKey) ?>"
     <?= $forceVisible ? '' : 'hidden' ?>>
    <span id="livre_game_links_label" class="magazine-series-tags-field__label">Jeux liés</span>
    <p class="hint">
        Recherchez les jeux du catalogue dont parle ce livre (guides, artbooks, novels…).
    </p>

    <ul class="magazine-series-tags-field__list livre-game-links-field__list" role="list"
        aria-labelledby="livre_game_links_label"
        data-livre-game-links-list>
        <?php foreach ($linkedGames as $linkedGame): ?>
            <?php
            $gameOeuvreId = (int) ($linkedGame['oeuvre_id'] ?? 0);
            $gameTitle = (string) ($linkedGame['display_titre'] ?? $linkedGame['titre'] ?? '');
            if ($gameOeuvreId <= 0 || $gameTitle === '') {
                continue;
            }
            ?>
            <li class="magazine-series-tags-field__item" role="listitem" data-game-oeuvre-id="<?= $gameOeuvreId ?>">
                <span class="magazine-tag magazine-tag--game-genre">
                    <span class="magazine-series-tags-field__text"><?= Moncine\View::escape($gameTitle) ?></span>
                    <button type="button"
                            class="magazine-series-tags-field__remove"
                            title="Retirer ce jeu"
                            aria-label="Retirer <?= Moncine\View::escape($gameTitle) ?>">×</button>
                </span>
                <input type="hidden" name="game_oeuvre_ids[]" value="<?= $gameOeuvreId ?>">
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="magazine-series-tags-field__add catalog-title-autocomplete"
         data-livre-game-link-search
         data-search-url="/rechercher-jeux-catalogue.php">
        <label class="visually-hidden" for="livre_game_link_search">Rechercher un jeu</label>
        <input type="search"
               id="livre_game_link_search"
               class="magazine-series-tags-field__input catalog-title-autocomplete__input"
               placeholder="Titre du jeu…"
               autocomplete="off"
               autocapitalize="off"
               spellcheck="false"
               aria-autocomplete="list"
               aria-controls="livre-game-link-suggestions">
        <ul class="catalog-title-autocomplete__list" id="livre-game-link-suggestions"
            role="listbox" hidden></ul>
    </div>
</div>
