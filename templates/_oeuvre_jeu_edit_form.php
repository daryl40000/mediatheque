<?php
/**
 * Formulaire de correction d’une fiche jeu catalogue.
 *
 * @var array<string, mixed> $game
 * @var int $oeuvreId
 * @var bool $editOpen
 * @var string $saveError
 * @var string $catalogSearch
 * @var string $catalogSort
 * @var string $catalogDir
 * @var int $catalogPage
 * @var array<string, string> $platformChoices
 * @var list<string> $knownGenres
 */
$editOpen = $editOpen ?? false;
$saveError = $saveError ?? '';
$catalogSearch = $catalogSearch ?? '';
$catalogSort = $catalogSort ?? 'titre';
$catalogDir = $catalogDir ?? 'asc';
$catalogPage = (int) ($catalogPage ?? 1);
?>
<details class="film-edit-panel"<?= $editOpen ? ' open' : '' ?>>
    <summary class="film-edit-panel__summary">Modifier la fiche catalogue jeu</summary>

    <p class="hint">
        Corrigez le titre, la plateforme, les genres, la jaquette, etc. Réservé aux administrateurs du catalogue.
    </p>

    <?php if ($saveError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
    <?php endif; ?>

    <form method="post" action="/enregistrer-modification-oeuvre-jeu.php" class="film-edit-form import-form"
          enctype="multipart/form-data" data-game-catalog-url="/rechercher-jeux-catalogue.php"
          data-catalog-edit-only="1" data-can-manage-catalog="1">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="oeuvre_id" value="<?= (int) $oeuvreId ?>">
        <input type="hidden" name="catalog_edit_only" value="1">
        <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch) ?>">
        <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort) ?>">
        <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir) ?>">
        <input type="hidden" name="catalog_page" value="<?= max(1, $catalogPage) ?>">

        <?php
        $catalogEditOnly = true;
        require MONCINE_ROOT . '/templates/_game_form_fields.php';
        ?>

        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
    </form>
</details>
