<?php
/**
 * Champs formulaire tome BD (série déjà choisie).
 *
 * @var array<string, mixed> $series
 * @var array<string, string> $supportChoices
 * @var list<string> $knownGenres
 * @var array<string, mixed>|null $album
 * @var int $prefillOeuvreId
 * @var int $suggestTomeNumero
 * @var bool $showPossessionHint
 */
$album = is_array($album ?? null) ? $album : null;
$prefillOeuvreId = (int) ($prefillOeuvreId ?? ($album['oeuvre_id'] ?? 0));
$seriesId = (int) ($series['id'] ?? 0);
$suggestTomeNumero = (int) ($suggestTomeNumero ?? 1);
$isPossessed = $album !== null && !empty($album['is_possessed']);
$showPossessionHint = ($showPossessionHint ?? true) === true;
?>
<input type="hidden" name="series_id" value="<?= $seriesId ?>">
<input type="hidden" name="oeuvre_id" id="bd_oeuvre_id" value="<?= $prefillOeuvreId > 0 ? $prefillOeuvreId : '' ?>">

<label for="bd_tome_numero">Numéro de tome <span class="required">*</span></label>
<input type="number" name="tome_numero" id="bd_tome_numero" min="1" step="1" required
       value="<?= (int) ($album['tome_numero'] ?? 0) > 0
           ? (int) $album['tome_numero']
           : $suggestTomeNumero ?>">

<label for="bd_tome_label">Libellé alternatif (optionnel)</label>
<input type="text" name="tome_label" id="bd_tome_label"
       value="<?= Moncine\View::escape((string) ($album['tome_label'] ?? '')) ?>"
       placeholder="Ex. Intégrale 1, HS…">

<label for="bd_titre">Titre spécifique (optionnel)</label>
<input type="text" name="titre" id="bd_titre"
       value="<?= Moncine\View::escape((string) ($album['titre'] ?? '')) ?>"
       placeholder="Laisser vide pour « Série — Tome N »">

<label for="bd_annee">Année</label>
<input type="number" name="annee" id="bd_annee" min="0" max="2100"
       value="<?= (int) ($album['annee'] ?? 0) > 0 ? (int) $album['annee'] : '' ?>">

<label for="bd_scenariste">Scénariste</label>
<input type="text" name="scenariste" id="bd_scenariste"
       value="<?= Moncine\View::escape((string) ($album['scenariste'] ?? '')) ?>">

<label for="bd_dessinateur">Dessinateur</label>
<input type="text" name="dessinateur" id="bd_dessinateur"
       value="<?= Moncine\View::escape((string) ($album['dessinateur'] ?? '')) ?>">

<label for="bd_editeur">Éditeur du tome</label>
<input type="text" name="editeur" id="bd_editeur"
       value="<?= Moncine\View::escape((string) ($album['editeur'] ?? '')) ?>">

<label for="bd_genre">Genre</label>
<input type="text" name="genre" id="bd_genre" list="bd_genre_list"
       value="<?= Moncine\View::escape((string) ($album['genre'] ?? '')) ?>">
<?php if (($knownGenres ?? []) !== []): ?>
    <datalist id="bd_genre_list">
        <?php foreach ($knownGenres as $genre): ?>
            <option value="<?= Moncine\View::escape($genre) ?>"></option>
        <?php endforeach; ?>
    </datalist>
<?php endif; ?>

<fieldset class="magazine-support-fieldset">
    <legend>Exemplaire</legend>
    <label class="checkbox">
        <input type="checkbox" name="support_possede" value="1" id="bd_support_possede"
            <?= $isPossessed ? ' checked' : '' ?>>
        Je possède cet exemplaire
    </label>
    <?php if ($showPossessionHint): ?>
        <p class="hint">
            Décochez si vous voulez seulement référencer le tome dans la série sans l’avoir :
            il apparaîtra <strong>grisé</strong> dans la liste (comme les numéros de magazines non possédés).
        </p>
    <?php endif; ?>
    <label for="bd_support">Support physique</label>
    <select name="support_physique" id="bd_support">
        <option value="">— Choisir —</option>
        <?php foreach ($supportChoices as $key => $label): ?>
            <option value="<?= Moncine\View::escape($key) ?>"
                <?= (($album['support_physique'] ?? '') === $key) ? ' selected' : '' ?>>
                <?= Moncine\View::escape($label) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="hint">Si vous cochez « Je possède » sans choisir, le support <strong>Album</strong> sera utilisé par défaut.</p>
</fieldset>

<label for="bd_synopsis">Résumé (optionnel)</label>
<textarea name="synopsis" id="bd_synopsis" rows="4"><?= Moncine\View::escape((string) ($album['synopsis'] ?? '')) ?></textarea>
