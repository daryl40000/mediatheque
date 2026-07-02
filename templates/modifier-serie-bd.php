<?php
/** @var array<string, string> $kindChoices */
/** @var array<string, mixed> $series */
/** @var string $kind */
/** @var string $error */
/** @var bool $saved */
$seriesId = (int) ($series['id'] ?? 0);
$hasDedicatedPoster = trim((string) ($series['poster_url'] ?? '')) !== '';
$posterSrc = Moncine\View::seriesPosterSrc($series);
?>
<section>
    <h1>Modifier la série</h1>
    <p class="lead">Mettez à jour les informations ou changez la couverture affichée dans la liste « Mes BD ».</p>
    <p>
        <a href="<?= Moncine\View::escape(Moncine\View::bdSeriesUrl($seriesId)) ?>" class="btn btn-secondary btn-sm">← Retour à la série</a>
    </p>

    <?php if ($saved): ?>
        <div class="alert alert-success">Modifications enregistrées.</div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
    <?php endif; ?>

    <?php if ($posterSrc !== ''): ?>
        <p>
            <img src="<?= $posterSrc ?>" alt="Couverture actuelle" class="magazine-cover magazine-cover--header">
        </p>
        <?php if (!$hasDedicatedPoster): ?>
            <p class="hint">Couverture affichée automatiquement depuis le tome 1 du catalogue. Téléversez une image ci-dessous pour une couverture de série dédiée.</p>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" action="/enregistrer-modification-serie-bd.php" class="import-form" enctype="multipart/form-data">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="series_id" value="<?= $seriesId ?>">

        <label for="titre">Titre de la série <span class="required">*</span></label>
        <input type="text" name="titre" id="titre" required maxlength="200"
               value="<?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?>">

        <label for="kind">Type</label>
        <select name="kind" id="kind">
            <?php foreach ($kindChoices as $key => $label): ?>
                <option value="<?= Moncine\View::escape($key) ?>"
                    <?= $kind === $key ? ' selected' : '' ?>>
                    <?= Moncine\View::escape($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="cover_file">Couverture de la série (JPEG, PNG, WebP)</label>
        <input type="file" name="cover_file" id="cover_file" accept="image/jpeg,image/png,image/webp">
        <p class="hint">Optionnel. Sans image dédiée, la couverture du tome 1 est utilisée automatiquement.</p>

        <label for="editeur">Éditeur</label>
        <input type="text" name="editeur" id="editeur" maxlength="120"
               value="<?= Moncine\View::escape((string) ($series['editeur'] ?? '')) ?>">

        <label for="notes">Notes</label>
        <textarea name="notes" id="notes" rows="3"><?= Moncine\View::escape((string) ($series['notes'] ?? '')) ?></textarea>

        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
</section>
