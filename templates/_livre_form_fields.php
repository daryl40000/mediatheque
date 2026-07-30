<?php
/**
 * Champs communs du formulaire livre (ajout / modification).
 *
 * @var array<string, mixed> $book
 * @var list<array<string, mixed>> $linkedGames
 * @var list<string> $knownCategories
 * @var list<string> $sagaSuggestions
 */
$book = $book ?? [];
$linkedGames = $linkedGames ?? [];
$knownCategories = $knownCategories ?? Moncine\LivreCategory::suggestionLabels();
$sagaSuggestions = $sagaSuggestions ?? [];
$livreCategoriesList = Moncine\LivreCategory::listForBook($book);
$forceGameLinks = Moncine\LivreCategory::includesJeuxVideo($book);
?>
<div class="form-grid">
    <div class="form-field">
        <label for="livre_titre">Titre <span class="required">*</span></label>
        <input type="text" name="titre" id="livre_titre" required maxlength="255"
               value="<?= Moncine\View::escape((string) ($book['titre'] ?? '')) ?>">
    </div>

    <div class="form-field">
        <label for="livre_sous_titre">Sous-titre</label>
        <input type="text" name="sous_titre" id="livre_sous_titre" maxlength="255"
               value="<?= Moncine\View::escape((string) ($book['sous_titre'] ?? '')) ?>"
               placeholder="Ex. La Communauté de l’Anneau">
    </div>

    <div class="form-field">
        <label for="livre_auteur">Auteur</label>
        <input type="text" name="auteur" id="livre_auteur" maxlength="255"
               value="<?= Moncine\View::escape((string) ($book['auteur'] ?? '')) ?>">
    </div>

    <div class="form-field">
        <label for="livre_annee">Année</label>
        <input type="number" name="annee" id="livre_annee" min="0" max="2100"
               value="<?= (int) ($book['annee'] ?? 0) > 0 ? (int) $book['annee'] : '' ?>">
    </div>

    <div class="form-field">
        <label for="livre_editeur">Éditeur</label>
        <input type="text" name="editeur" id="livre_editeur" maxlength="255"
               value="<?= Moncine\View::escape((string) ($book['editeur'] ?? '')) ?>">
    </div>

    <div class="form-field">
        <label for="livre_isbn">ISBN</label>
        <input type="text" name="isbn" id="livre_isbn" maxlength="32"
               value="<?= Moncine\View::escape((string) ($book['isbn'] ?? '')) ?>">
    </div>

    <div class="form-field">
        <label for="livre_pages">Nombre de pages</label>
        <input type="number" name="pages" id="livre_pages" min="0" max="99999"
               value="<?= (int) ($book['pages'] ?? 0) > 0 ? (int) $book['pages'] : '' ?>">
    </div>

    <div class="form-field">
        <label for="livre_langue">Langue</label>
        <input type="text" name="langue" id="livre_langue" maxlength="16"
               value="<?= Moncine\View::escape((string) ($book['langue'] ?? 'fr')) ?>">
    </div>

    <div class="form-field">
        <label for="livre_collection_label">Collection / série éditoriale</label>
        <input type="text" name="collection_label" id="livre_collection_label" maxlength="255"
               value="<?= Moncine\View::escape((string) ($book['collection_label'] ?? '')) ?>"
               placeholder="Ex. Bibliothèque de l’Imaginaire">
    </div>

    <div class="form-field">
        <label for="livre_saga">Saga</label>
        <input type="text" name="saga" id="livre_saga" maxlength="120"
               value="<?= Moncine\View::escape((string) ($book['saga'] ?? '')) ?>"
               list="livre-saga-suggestions"
               placeholder="Ex. Harry Potter, Dune"
               autocomplete="off">
        <?php if ($sagaSuggestions !== []): ?>
            <datalist id="livre-saga-suggestions">
                <?php foreach ($sagaSuggestions as $sagaHint): ?>
                    <option value="<?= Moncine\View::escape((string) $sagaHint) ?>">
                <?php endforeach; ?>
            </datalist>
        <?php endif; ?>
        <p class="hint">Regroupe plusieurs livres d’une même histoire (comme pour les films et les jeux).</p>
    </div>

    <div class="form-field">
        <label for="livre_saga_ordre">N° dans la saga</label>
        <input type="number" name="saga_ordre" id="livre_saga_ordre" min="1" max="999" step="1"
               value="<?= (int) ($book['saga_ordre'] ?? 0) > 0 ? (int) $book['saga_ordre'] : '' ?>">
    </div>

    <div class="form-field">
        <label for="livre_support">Support</label>
        <select name="support_physique" id="livre_support">
            <?php
            $support = (string) ($book['support_physique'] ?? 'papier');
            $supports = [
                'papier' => 'Papier',
                'ebook' => 'Numérique (ebook)',
                'autre' => 'Autre',
            ];
            foreach ($supports as $key => $label):
                ?>
                <option value="<?= Moncine\View::escape($key) ?>"<?= $support === $key ? ' selected' : '' ?>>
                    <?= Moncine\View::escape($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php require MONCINE_ROOT . '/templates/_livre_categories_field.php'; ?>

<?php
$forceVisible = $forceGameLinks;
require MONCINE_ROOT . '/templates/_livre_game_links_field.php';
?>

<div class="form-field">
    <label for="livre_synopsis">Résumé</label>
    <textarea name="synopsis" id="livre_synopsis" rows="5"><?= Moncine\View::escape((string) ($book['synopsis'] ?? '')) ?></textarea>
</div>

<fieldset class="livre-covers-fieldset">
    <legend>Couvertures</legend>

    <div class="form-field">
        <label for="livre_poster_url">URL de la couverture (1re de couverture)</label>
        <!-- type="text" (pas "url") : les couvertures locales sont des chemins /posters/… -->
        <input type="text" name="poster_url" id="livre_poster_url" maxlength="500"
               value="<?= Moncine\View::escape((string) ($book['poster_url'] ?? '')) ?>"
               placeholder="https://… ou chemin local">
    </div>

    <div class="form-field">
        <label for="livre_cover_file">Ou fichier image — couverture</label>
        <input type="file" name="cover_file" id="livre_cover_file" accept="image/*">
    </div>

    <div class="form-field">
        <label for="livre_back_cover_url">URL de la 4e de couverture</label>
        <input type="text" name="back_cover_url" id="livre_back_cover_url" maxlength="500"
               value="<?= Moncine\View::escape((string) ($book['back_cover_url'] ?? '')) ?>"
               placeholder="https://… ou chemin local">
    </div>

    <div class="form-field">
        <label for="livre_back_cover_file">Ou fichier image — 4e de couverture</label>
        <input type="file" name="back_cover_file" id="livre_back_cover_file" accept="image/*">
        <p class="hint">La 4e de couverture (dos du livre) s’affiche à côté de la couverture sur la fiche.</p>
    </div>
</fieldset>
