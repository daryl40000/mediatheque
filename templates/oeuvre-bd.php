<?php
/** @var array<string, mixed>|null $album */
/** @var array<string, mixed>|null $oeuvre */
/** @var int $libraryCount */
/** @var Moncine\CatalogListContext $catalogListContext */
/** @var string $catalogueBackUrl */
/** @var array<string, string> $kindChoices */
?>
<section>
    <?php if ($album === null): ?>
        <h1>Album introuvable</h1>
        <p class="hint">Cette fiche catalogue n’existe pas.</p>
        <p><a href="<?= Moncine\View::escape($catalogueBackUrl) ?>" class="btn btn-secondary">← Catalogue</a></p>
    <?php else: ?>
        <p><a href="<?= Moncine\View::escape($catalogueBackUrl) ?>" class="btn btn-secondary btn-sm">← Catalogue</a></p>
        <h1><?= Moncine\View::escape((string) ($album['display_titre'] ?? '')) ?></h1>
        <p class="hint"><?= (int) $libraryCount ?> exemplaire<?= $libraryCount > 1 ? 's' : '' ?> en bibliothèque.</p>

        <dl class="film-meta">
            <?php if ((string) ($album['series_titre'] ?? '') !== ''): ?>
                <div><dt>Série</dt><dd><?= Moncine\View::escape((string) $album['series_titre']) ?></dd></div>
            <?php endif; ?>
            <div><dt>Type</dt><dd><?= Moncine\View::escape((string) ($album['kind_label'] ?? '')) ?></dd></div>
            <div><dt>Scénariste</dt><dd><?= Moncine\View::escape((string) ($album['scenariste'] ?? '')) ?: '—' ?></dd></div>
            <div><dt>Dessinateur</dt><dd><?= Moncine\View::escape((string) ($album['dessinateur'] ?? '')) ?: '—' ?></dd></div>
            <div><dt>Éditeur</dt><dd><?= Moncine\View::escape((string) ($album['editeur'] ?? '')) ?: '—' ?></dd></div>
        </dl>
    <?php endif; ?>
</section>
