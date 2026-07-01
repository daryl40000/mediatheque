<?php
/**
 * Couverture d’un tome BD : fichier upload ou URL distante.
 *
 * @var array<string, mixed>|null $album
 */
$album = is_array($album ?? null) ? $album : null;
$coverInputId = (string) ($coverInputId ?? 'bd_cover_file');
$posterUrlInputId = (string) ($posterUrlInputId ?? 'bd_poster_url');
?>
<label for="<?= Moncine\View::escape($coverInputId) ?>">Couverture (JPEG, PNG, WebP)</label>
<input type="file" name="cover_file" id="<?= Moncine\View::escape($coverInputId) ?>"
       accept="image/jpeg,image/png,image/webp">
<p class="hint">Taille max. <?= Moncine\View::escape(Moncine\UploadLimits::maxPosterBytesLabel()) ?>.</p>

<label for="<?= Moncine\View::escape($posterUrlInputId) ?>">Ou URL de la couverture (facultatif)</label>
<input type="text" name="poster_url" id="<?= Moncine\View::escape($posterUrlInputId) ?>" maxlength="500"
       placeholder="https://… ou /posters/123.jpg"
       value="<?= Moncine\View::escape((string) ($album['poster_url'] ?? '')) ?>">
<p class="hint">
    Chemin local <code>/posters/…</code> ou URL <strong>HTTPS</strong> ;
    l’image distante sera téléchargée sur le serveur (comme pour les jeux vidéo).
</p>
