<?php
/**
 * Liste déroulante « type de contenu » (film, documentaire, série…).
 *
 * @var array<string, mixed>|null $film Fiche existante (null à l’ajout)
 * @var string $fieldPrefix Préfixe des id HTML (ex. edit, add)
 */

$fieldPrefix = $fieldPrefix ?? 'edit';
$film = $film ?? null;

$currentProfile = $film !== null
    ? Moncine\TmdbContentProfile::fromTmdbFields(
        (string) ($film['tmdb_media_type'] ?? ''),
        (string) ($film['tmdb_tv_kind'] ?? '')
    )
    : Moncine\TmdbContentProfile::FILM;

$selectId = $fieldPrefix . '_tmdb_content_profile';
?>
<label for="<?= Moncine\View::escape($selectId) ?>">Type de contenu</label>
<select name="tmdb_content_profile" id="<?= Moncine\View::escape($selectId) ?>">
    <?php foreach (Moncine\TmdbContentProfile::choices() as $value => $label): ?>
        <option value="<?= Moncine\View::escape($value) ?>"<?= $currentProfile === $value ? ' selected' : '' ?>>
            <?= Moncine\View::escape($label) ?>
        </option>
    <?php endforeach; ?>
</select>
<p class="hint">
    Corrigez ici si TMDB a classé le titre en documentaire ou en série par erreur.
    <?php if ($film !== null && (int) ($film['tmdb_id'] ?? 0) > 0): ?>
        Type actuel affiché :
        <strong><?= Moncine\View::escape(Moncine\TmdbMediaType::label(
            (string) ($film['tmdb_media_type'] ?? ''),
            (string) ($film['tmdb_tv_kind'] ?? '')
        )) ?></strong>.
    <?php endif; ?>
</p>
