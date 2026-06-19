<?php
/**
 * Enrichissement IGDB (fiche jeu catalogue ou bibliothèque).
 *
 * @var string $enrichTarget oeuvre|game
 * @var int $entityId
 * @var bool $hasIgdbCredentials
 * @var string|null $enrichStatus ok|not_found|error
 * @var string $enrichMessage
 * @var string $catalogSearch
 * @var string $catalogSort
 * @var string $catalogDir
 * @var int $catalogPage
 * @var int $currentIgdbId
 */
$enrichTarget = $enrichTarget ?? 'oeuvre';
$entityId = (int) ($entityId ?? 0);
$enrichMessage = $enrichMessage ?? '';
$catalogSearch = $catalogSearch ?? '';
$catalogSort = $catalogSort ?? 'titre';
$catalogDir = $catalogDir ?? 'asc';
$catalogPage = max(1, (int) ($catalogPage ?? 1));
$currentIgdbId = (int) ($currentIgdbId ?? 0);

$formAction = $enrichTarget === 'game' ? '/enrichir-jeu.php' : '/enrichir-oeuvre-jeu.php';
$idFieldName = $enrichTarget === 'game' ? 'game_id' : 'oeuvre_id';
$panelTitle = $enrichTarget === 'game'
    ? 'Enrichir cette fiche'
    : 'Enrichir cette fiche jeu';
$igdbPublicUrl = $currentIgdbId > 0 ? Moncine\IgdbClient::publicUrl($currentIgdbId) : '';
?>
<div class="enrich-film-panel enrich-game-panel">
    <h2 class="enrich-film-panel__title"><?= Moncine\View::escape($panelTitle) ?></h2>
    <?php if ($hasIgdbCredentials ?? false): ?>
        <p class="hint">
            <strong>IGDB</strong> complète la fiche : jaquette (téléchargée localement), année,
            studio (développeur), éditeur et genres (traduits en français).
            Vous pouvez aussi coller un identifiant IGDB (<code>1942</code> ou URL igdb.com).
            <?php if ($enrichTarget === 'game'): ?>
                La fiche catalogue partagée en profitera aussi.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($enrichMessage)): ?>
        <p class="alert <?= ($enrichStatus ?? '') === 'ok' ? 'alert-success' : 'alert-warning' ?>">
            <?= Moncine\View::escape($enrichMessage) ?>
        </p>
    <?php endif; ?>

    <?php if (!($hasIgdbCredentials ?? false)): ?>
        <p class="hint">
            <a href="/import.php">Configurez les identifiants IGDB (Twitch Developer)</a>
            sur la page Importer pour activer l’enrichissement.
        </p>
    <?php else: ?>
        <form method="post" action="<?= Moncine\View::escape($formAction) ?>" class="inline-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="<?= Moncine\View::escape($idFieldName) ?>" value="<?= $entityId ?>">
            <?php if ($enrichTarget === 'oeuvre'): ?>
                <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch) ?>">
                <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort) ?>">
                <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir) ?>">
                <input type="hidden" name="catalog_page" value="<?= $catalogPage ?>">
            <?php endif; ?>
            <input type="hidden" name="action" value="enrich">
            <button type="submit" class="btn btn-accent">Enrichir par le titre</button>
        </form>

        <?php if (($enrichStatus ?? '') === 'not_found' || ($enrichStatus ?? '') === 'error'): ?>
            <p class="hint">
                Introuvable par le titre ? Collez l’identifiant IGDB ci-dessous.
            </p>
        <?php endif; ?>

        <div class="enrich-tmdb-correct">
            <p class="hint">
                <strong>Mauvaise fiche ?</strong> Collez l’URL ou le numéro IGDB
                (sur <a href="https://www.igdb.com/" target="_blank" rel="noopener">igdb.com</a>) :
                <code>1942</code> ou <code>https://www.igdb.com/games/the-witcher-3-wild-hunt</code>.
            </p>
            <form method="post" action="<?= Moncine\View::escape($formAction) ?>" class="import-form enrich-tmdb-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="<?= Moncine\View::escape($idFieldName) ?>" value="<?= $entityId ?>">
                <?php if ($enrichTarget === 'oeuvre'): ?>
                    <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch) ?>">
                    <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort) ?>">
                    <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir) ?>">
                    <input type="hidden" name="catalog_page" value="<?= $catalogPage ?>">
                <?php endif; ?>
                <input type="hidden" name="action" value="igdb">
                <label for="igdb_id_<?= $entityId ?>_<?= Moncine\View::escape($enrichTarget) ?>">Identifiant IGDB</label>
                <input type="text" name="igdb_id" id="igdb_id_<?= $entityId ?>_<?= Moncine\View::escape($enrichTarget) ?>"
                       value="<?= $currentIgdbId > 0 ? (int) $currentIgdbId : '' ?>"
                       placeholder="1942 ou URL igdb.com/games/…" required>
                <button type="submit" class="btn btn-secondary">Corriger avec cet ID IGDB</button>
            </form>
        </div>

        <?php if ($currentIgdbId > 0 && $igdbPublicUrl !== ''): ?>
            <p class="hint">
                IGDB enregistré :
                <a href="<?= Moncine\View::escape($igdbPublicUrl) ?>" target="_blank" rel="noopener">
                    #<?= (int) $currentIgdbId ?>
                </a>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
