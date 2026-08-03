<?php
/**
 * Formulaire de correction d’un numéro magazine catalogue.
 *
 * @var array<string, mixed> $issue
 * @var int $oeuvreId
 * @var bool $editOpen
 * @var string $saveError
 * @var string $catalogSearch
 * @var string $catalogSort
 * @var string $catalogDir
 * @var int $catalogPage
 */
$editOpen = $editOpen ?? false;
$saveError = $saveError ?? '';
$catalogSearch = $catalogSearch ?? '';
$catalogSort = $catalogSort ?? 'titre';
$catalogDir = $catalogDir ?? 'asc';
$catalogPage = (int) ($catalogPage ?? 1);
?>
<details class="film-edit-panel"<?= $editOpen ? ' open' : '' ?>>
    <summary class="film-edit-panel__summary">Modifier la fiche catalogue magazine</summary>

    <p class="hint">
        Corrigez le numéro, la date de parution, le sommaire, etc. La série reste inchangée.
    </p>

    <?php if ($saveError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
    <?php endif; ?>

    <form method="post" action="/enregistrer-modification-oeuvre-magazine.php" class="film-edit-form import-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="oeuvre_id" value="<?= (int) $oeuvreId ?>">
        <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch) ?>">
        <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort) ?>">
        <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir) ?>">
        <input type="hidden" name="catalog_page" value="<?= max(1, $catalogPage) ?>">

        <label for="oeuvre_mag_numero">Numéro <span class="required">*</span></label>
        <input type="text" name="numero" id="oeuvre_mag_numero" required maxlength="40"
               value="<?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?>">

        <?php
        // Ordre de tri dans la liste des numéros de la série (ex. 42 ou 42.5 pour un HS).
        $numeroOrdreValue = (float) ($issue['numero_ordre'] ?? 0);
        $numeroOrdreDisplay = $numeroOrdreValue > 0
            ? rtrim(rtrim(number_format($numeroOrdreValue, 1, '.', ''), '0'), '.')
            : '';
        ?>
        <label for="oeuvre_mag_numero_ordre">Ordre de tri</label>
        <input type="number" step="0.1" name="numero_ordre" id="oeuvre_mag_numero_ordre"
               value="<?= Moncine\View::escape($numeroOrdreDisplay) ?>">
        <p class="hint">
            Sert à classer les numéros dans la série (1, 2, 3…).
            Pour un hors-série entre deux numéros, utilisez un demi (ex. 42.5).
        </p>

        <label for="oeuvre_mag_date">Date de parution</label>
        <input type="date" name="date_parution" id="oeuvre_mag_date"
               value="<?= Moncine\View::escape((string) ($issue['date_parution'] ?? '')) ?>">

        <label for="oeuvre_mag_pages">Nombre de pages</label>
        <input type="number" name="pages" id="oeuvre_mag_pages" min="0" max="9999" step="1"
               value="<?= (int) ($issue['pages'] ?? 0) > 0 ? (int) $issue['pages'] : '' ?>">

        <label class="checkbox-inline">
            <input type="checkbox" name="est_hors_serie" id="oeuvre_mag_est_hors_serie" value="1"<?= !empty($issue['est_hors_serie']) ? ' checked' : '' ?>>
            Numéro hors-série
        </label>

        <label for="oeuvre_mag_poster">Couverture (URL ou chemin local)</label>
        <input type="text" name="poster_url" id="oeuvre_mag_poster" maxlength="500"
               placeholder="https://… ou /posters/123.jpg"
               value="<?= Moncine\View::escape((string) ($issue['poster_url'] ?? '')) ?>">
        <p class="hint">
            Chemin local <code>/posters/…</code> ou URL <strong>HTTPS</strong> ;
            vous pouvez aussi envoyer un fichier via la section « Couverture » ci-dessous.
        </p>

        <label for="oeuvre_mag_sommaire">Sommaire</label>
        <textarea name="sommaire" id="oeuvre_mag_sommaire" rows="5"><?= Moncine\View::escape((string) ($issue['sommaire'] ?? '')) ?></textarea>

        <label for="oeuvre_mag_external_url">Lien « consulter en ligne » (URL HTTPS)</label>
        <input type="url" name="external_url" id="oeuvre_mag_external_url" maxlength="500"
               placeholder="https://www.abandonware-magazines.org/affiche_mag.php?mag=…&amp;num=…"
               value="<?= Moncine\View::escape((string) ($issue['external_url'] ?? '')) ?>">
        <p class="hint">
            Lien vers ce numéro consultable en ligne (ex. Abandonware Magazines). Facultatif.
        </p>

        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
    </form>
</details>
