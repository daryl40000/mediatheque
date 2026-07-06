<?php
/**
 * Détails BD en deux colonnes (fiche bibliothèque).
 *
 * @var array<string, mixed> $album
 * @var bool $isWishlist
 */
$album = $album ?? [];
$isWishlist = $isWishlist ?? false;
$isPossessed = !empty($album['is_possessed']);
$readAtLabel = (string) ($album['read_at_label'] ?? '');
?>
<div class="game-detail-facts-grid">
    <dl class="film-facts game-detail-facts-grid__col">
        <?php if ((string) ($album['scenariste'] ?? '') !== ''): ?>
            <dt>Scénariste</dt>
            <dd><?= Moncine\View::escape((string) $album['scenariste']) ?></dd>
        <?php endif; ?>
        <?php if ((string) ($album['dessinateur'] ?? '') !== ''): ?>
            <dt>Dessinateur</dt>
            <dd><?= Moncine\View::escape((string) $album['dessinateur']) ?></dd>
        <?php endif; ?>
        <?php if ((string) ($album['editeur'] ?? '') !== ''): ?>
            <dt>Éditeur</dt>
            <dd><?= Moncine\View::escape((string) $album['editeur']) ?></dd>
        <?php endif; ?>
        <?php if ((string) ($album['genre'] ?? '') !== ''): ?>
            <dt>Genre</dt>
            <dd><span class="magazine-tag magazine-tag--game-genre"><?= Moncine\View::escape((string) $album['genre']) ?></span></dd>
        <?php endif; ?>
    </dl>

    <dl class="film-facts game-detail-facts-grid__col">
        <dt>Exemplaire</dt>
        <dd>
            <?php if ($isPossessed): ?>
                <?= Moncine\View::escape((string) ($album['support_label'] ?? '')) ?>
            <?php else: ?>
                <span class="magazine-tag magazine-tag--none">Non possédé</span>
            <?php endif; ?>
        </dd>
        <?php if ((string) ($album['added_at_label'] ?? '') !== ''): ?>
            <dt><?= $isWishlist ? 'Envie ajoutée le' : 'Ajouté le' ?></dt>
            <dd><?= Moncine\View::escape((string) $album['added_at_label']) ?></dd>
        <?php endif; ?>
        <?php if ($readAtLabel !== ''): ?>
            <dt>Dernière lecture</dt>
            <dd><?= Moncine\View::escape($readAtLabel) ?></dd>
        <?php endif; ?>
    </dl>
</div>
