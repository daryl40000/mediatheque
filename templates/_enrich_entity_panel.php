<?php
/**
 * Enrichissement TMDB (fiche film ou œuvre catalogue).
 *
 * @var string $enrichTarget film|oeuvre
 * @var int $entityId
 * @var bool $hasTmdbKey
 * @var string|null $enrichStatus ok|not_found|error
 * @var string $enrichMessage
 * @var string $returnPage film|resultat|oeuvre
 * @var int $currentTmdbId
 * @var string $currentTmdbMediaType
 * @var string $currentTmdbTvKind
 * @var string $catalogSearch
 * @var string $catalogSort
 * @var string $catalogDir
 * @var int $catalogPage
 */
$enrichTarget = $enrichTarget ?? 'film';
$entityId = (int) ($entityId ?? 0);
$enrichMessage = $enrichMessage ?? '';
$returnPage = $returnPage ?? 'film';
$catalogSearch = $catalogSearch ?? '';
$catalogSort = $catalogSort ?? 'titre';
$catalogDir = $catalogDir ?? 'asc';
$catalogPage = max(1, (int) ($catalogPage ?? 1));
$currentTmdbId = (int) ($currentTmdbId ?? 0);
$currentTmdbMediaType = (string) ($currentTmdbMediaType ?? '');
$currentTmdbTvKind = (string) ($currentTmdbTvKind ?? '');

$formAction = $enrichTarget === 'oeuvre' ? '/enrichir-oeuvre.php' : '/enrichir-film.php';
$idFieldName = $enrichTarget === 'oeuvre' ? 'oeuvre_id' : 'film_id';
$panelTitle = $enrichTarget === 'oeuvre'
    ? 'Enrichir cette fiche catalogue'
    : 'Enrichir cette fiche';

$tmdbPublicUrl = $currentTmdbId > 0
    ? Moncine\TmdbMediaType::publicUrl($currentTmdbId, $currentTmdbMediaType)
    : '';
?>
<div class="enrich-film-panel">
    <h2 class="enrich-film-panel__title"><?= Moncine\View::escape($panelTitle) ?></h2>
    <?php if ($hasTmdbKey ?? false): ?>
        <p class="hint">
            TMDB complète la fiche selon sa <strong>catégorie</strong> (film, série, documentaire, spectacle) :
            synopsis en français, affiche, année, genres, réalisateur/créateur et acteurs.
            Vous pouvez aussi coller un identifiant TMDB (<code>/movie/…</code> ou <code>/tv/…</code>).
            <?php if ($enrichTarget === 'oeuvre'): ?>
                Les films déjà liés à cette œuvre dans les bibliothèques en profiteront aussi.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($enrichMessage)): ?>
        <p class="alert <?= ($enrichStatus ?? '') === 'ok' ? 'alert-success' : 'alert-warning' ?>">
            <?= Moncine\View::escape($enrichMessage) ?>
        </p>
    <?php endif; ?>

    <?php if (!($hasTmdbKey ?? false)): ?>
        <p class="hint">
            <a href="/import.php">Configurez une clé API TMDB</a> sur la page Importer pour activer l’enrichissement.
        </p>
    <?php else: ?>
        <form method="post" action="<?= Moncine\View::escape($formAction) ?>" class="inline-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="<?= Moncine\View::escape($idFieldName) ?>" value="<?= $entityId ?>">
            <input type="hidden" name="return" value="<?= Moncine\View::escape($returnPage) ?>">
            <?php if ($returnPage === 'oeuvre'): ?>
                <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch) ?>">
                <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort) ?>">
                <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir) ?>">
                <input type="hidden" name="catalog_page" value="<?= $catalogPage ?>">
            <?php elseif ($returnPage === 'film' && isset($filmListContext)): ?>
                <?php require MONCINE_ROOT . '/templates/_film_list_context_fields.php'; ?>
            <?php endif; ?>
            <input type="hidden" name="action" value="enrich">
            <button type="submit" class="btn btn-accent">Enrichir par le titre</button>
        </form>

        <?php if (($enrichStatus ?? '') === 'not_found' || ($enrichStatus ?? '') === 'error'): ?>
            <p class="hint">
                Introuvable par le titre ? Choisissez la bonne catégorie ou collez l’identifiant TMDB ci-dessous.
            </p>
        <?php endif; ?>

        <div class="enrich-tmdb-correct">
            <p class="hint">
                <strong>Mauvaise fiche ?</strong> Collez l’URL TMDB
                (sur <a href="https://www.themoviedb.org/" target="_blank" rel="noopener">themoviedb.org</a>) :
                <code>/movie/78</code> pour un film, <code>/tv/1396</code> pour une série.
            </p>
            <form method="post" action="<?= Moncine\View::escape($formAction) ?>" class="import-form enrich-tmdb-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="<?= Moncine\View::escape($idFieldName) ?>" value="<?= $entityId ?>">
                <input type="hidden" name="return" value="<?= Moncine\View::escape($returnPage) ?>">
                <?php if ($returnPage === 'oeuvre'): ?>
                    <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch) ?>">
                    <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort) ?>">
                    <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir) ?>">
                    <input type="hidden" name="catalog_page" value="<?= $catalogPage ?>">
                <?php elseif ($returnPage === 'film' && isset($filmListContext)): ?>
                    <?php require MONCINE_ROOT . '/templates/_film_list_context_fields.php'; ?>
                <?php endif; ?>
                <input type="hidden" name="action" value="tmdb">
                <label for="tmdb_id_<?= $entityId ?>_<?= Moncine\View::escape($enrichTarget) ?>">Identifiant TMDB</label>
                <input type="text" name="tmdb_id" id="tmdb_id_<?= $entityId ?>_<?= Moncine\View::escape($enrichTarget) ?>"
                       value="<?= $currentTmdbId > 0 ? (int) $currentTmdbId : '' ?>"
                       placeholder="78, /movie/78 ou /tv/11285" required>
                <button type="submit" class="btn btn-secondary">Corriger avec cet ID TMDB</button>
            </form>
        </div>

        <?php if ($currentTmdbId > 0 && $tmdbPublicUrl !== ''): ?>
            <p class="hint">
                TMDB enregistré (<?= Moncine\View::escape(Moncine\TmdbMediaType::label($currentTmdbMediaType, $currentTmdbTvKind)) ?>) :
                <a href="<?= Moncine\View::escape($tmdbPublicUrl) ?>" target="_blank" rel="noopener">
                    #<?= (int) $currentTmdbId ?>
                </a>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
