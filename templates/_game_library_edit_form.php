<?php
/**
 * Formulaire « mon exemplaire » sur la fiche jeu bibliothèque.
 *
 * @var array<string, mixed> $game
 * @var int $gameId
 * @var bool $editOpen
 * @var string $saveError
 * @var bool $canManageCatalog
 * @var array<string, string> $platformChoices
 */
$editOpen = $editOpen ?? false;
$saveError = $saveError ?? '';
$canManageCatalog = $canManageCatalog ?? false;
$platformChoices = $platformChoices ?? Moncine\GamePlatform::choices();
$gameId = (int) ($gameId ?? 0);
$oeuvreId = (int) ($game['oeuvre_id'] ?? 0);
$catalogPlatformKeys = $game['platform_list'] ?? Moncine\GamePlatformList::catalogKeysFromRow($game);
$catalogPlatformKeysAttr = Moncine\View::escape(implode(',', $catalogPlatformKeys));
?>
<details class="film-edit-panel game-library-edit-panel" id="game-library-edit-panel"<?= $editOpen ? ' open' : '' ?>>
    <summary class="film-edit-panel__summary">Modifier mon exemplaire</summary>

    <p class="hint">
        Indiquez <strong>vos plateformes</strong>, supports physiques, version dématérialisée (Steam, Battle.net…)
        et éventuellement un <strong>temps de jeu manuel</strong>.
        Le titre, la jaquette et les infos catalogue partagées ne se modifient pas ici.
    </p>

    <?php if ($canManageCatalog && $oeuvreId > 0): ?>
        <p class="hint">
            <a href="<?= Moncine\View::escape(Moncine\View::oeuvreJeuUrl($oeuvreId)) ?>">
                Modifier la fiche catalogue (admin)
            </a>
            ·
            <a href="<?= Moncine\View::escape(Moncine\View::gameEditUrl($gameId)) ?>">
                Modifier toute la fiche (admin)
            </a>
        </p>
    <?php elseif (!$canManageCatalog): ?>
        <p class="hint">
            Pour corriger le titre, la jaquette ou les infos catalogue, contactez l’administrateur du site.
        </p>
    <?php endif; ?>

    <?php if ($saveError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
    <?php endif; ?>

    <form method="post" action="/modifier-jeu-exemplaire.php" class="film-edit-form import-form"
          data-game-library-edit-form="1"
          data-catalog-platform-keys="<?= $catalogPlatformKeysAttr ?>"
          data-can-manage-catalog="0">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="game_id" value="<?= $gameId ?>">

        <?php
        $libraryEditOnly = true;
        require MONCINE_ROOT . '/templates/_game_form_fields.php';
        ?>

        <button type="submit" class="btn btn-primary">Enregistrer mon exemplaire</button>
    </form>
</details>
