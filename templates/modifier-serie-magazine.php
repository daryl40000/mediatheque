<?php
/** @var array<string, string> $publicationTypes */
/** @var array<string, mixed> $series */
/** @var string $error */
/** @var bool $saved */
$seriesId = (int) ($series['id'] ?? 0);
$posterSrc = Moncine\View::posterSrc(trim((string) ($series['poster_url'] ?? '')) ?: null);
?>
<section>
    <h1>Modifier la série</h1>
    <p class="lead">Mettez à jour les informations ou changez le logo / la couverture type de la revue.</p>
    <p>
        <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl($seriesId)) ?>" class="btn btn-secondary btn-sm">← Retour à la série</a>
    </p>

    <?php if ($saved): ?>
        <div class="alert alert-success">Modifications enregistrées.</div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
    <?php endif; ?>

    <?php if ($posterSrc !== ''): ?>
        <p>
            <img src="<?= $posterSrc ?>" alt="Logo actuel" class="magazine-cover magazine-cover--header">
        </p>
    <?php endif; ?>

    <form method="post" action="/enregistrer-modification-serie-magazine.php" class="import-form" enctype="multipart/form-data">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="series_id" value="<?= $seriesId ?>">

        <label for="titre">Titre de la série <span class="required">*</span></label>
        <input type="text" name="titre" id="titre" required maxlength="200"
               value="<?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?>">

        <label for="publication_type">Type de publication</label>
        <select name="publication_type" id="publication_type">
            <?php foreach ($publicationTypes as $key => $label): ?>
                <option value="<?= Moncine\View::escape($key) ?>"
                    <?= ($series['publication_type'] ?? '') === $key ? ' selected' : '' ?>>
                    <?= Moncine\View::escape($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php
        $seriesTagsList = Moncine\MagazineSeriesTag::parseList((string) ($series['tags'] ?? ''));
        require MONCINE_ROOT . '/templates/_magazine_series_tags_field.php';
        ?>

        <label for="cover_file">Logo ou couverture type (JPEG, PNG, WebP)</label>
        <input type="file" name="cover_file" id="cover_file" accept="image/jpeg,image/png,image/webp">
        <p class="hint">Image affichée dans la liste « Mes magazines ». Vous pourrez la remplacer en téléversant une nouvelle image.</p>

        <label for="editeur">Éditeur</label>
        <input type="text" name="editeur" id="editeur" maxlength="120"
               value="<?= Moncine\View::escape((string) ($series['editeur'] ?? '')) ?>">

        <label for="issn">ISSN</label>
        <input type="text" name="issn" id="issn" maxlength="20"
               value="<?= Moncine\View::escape((string) ($series['issn'] ?? '')) ?>">

        <label for="langue">Langue</label>
        <input type="text" name="langue" id="langue" maxlength="10"
               value="<?= Moncine\View::escape((string) ($series['langue'] ?? 'fr')) ?>">

        <label for="pays">Pays</label>
        <input type="text" name="pays" id="pays" maxlength="60"
               value="<?= Moncine\View::escape((string) ($series['pays'] ?? '')) ?>">

        <label for="date_debut">Première parution (optionnel)</label>
        <input type="date" name="date_debut" id="date_debut"
               value="<?= Moncine\View::escape((string) ($series['date_debut'] ?? '')) ?>">

        <label for="date_fin">Dernière parution (revue arrêtée)</label>
        <input type="date" name="date_fin" id="date_fin"
               value="<?= Moncine\View::escape((string) ($series['date_fin'] ?? '')) ?>">

        <label for="notes">Notes</label>
        <textarea name="notes" id="notes" rows="3"><?= Moncine\View::escape((string) ($series['notes'] ?? '')) ?></textarea>

        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
</section>
