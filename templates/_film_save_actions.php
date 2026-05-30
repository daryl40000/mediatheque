<?php
/**
 * Boutons d’enregistrement (simple ou avec enrichissement TMDB).
 *
 * @var string $cancelUrl
 * @var bool $hasTmdbKey
 * @var bool $canEnrichOnSave enrichissement TMDB réservé à l’admin catalogue
 * @var string $submitLabel
 * @var string $enrichLabel
 */
$cancelUrl = $cancelUrl ?? '/films.php';
$hasTmdbKey = $hasTmdbKey ?? false;
$canEnrichOnSave = $canEnrichOnSave ?? Moncine\UserContext::canManageCatalog();
$showEnrichButton = $hasTmdbKey && $canEnrichOnSave;
$submitLabel = $submitLabel ?? 'Enregistrer le film';
$enrichLabel = $enrichLabel ?? 'Enregistrer avec enrichissement';
?>
<div class="form-actions form-actions--split">
    <button type="submit" name="save_mode" value="save" class="btn btn-primary">
        <?= Moncine\View::escape($submitLabel) ?>
    </button>
    <?php if ($showEnrichButton): ?>
        <button type="submit" name="save_mode" value="enrich" class="btn btn-accent">
            <?= Moncine\View::escape($enrichLabel) ?>
        </button>
    <?php endif; ?>
    <a href="<?= Moncine\View::escape($cancelUrl) ?>" class="btn btn-ghost">Annuler</a>
    <?php if ($showEnrichButton || ($canEnrichOnSave && !$hasTmdbKey)): ?>
    <p class="hint form-actions__hint">
        <?php if ($showEnrichButton): ?>
            « <?= Moncine\View::escape($enrichLabel) ?> » enregistre la fiche puis complète synopsis, affiche,
            année, genres et acteurs via TMDB (selon le titre et la catégorie).
        <?php else: ?>
            <a href="/import.php">Configurez une clé API TMDB</a> pour activer l’enrichissement à l’enregistrement.
        <?php endif; ?>
    </p>
    <?php endif; ?>
</div>
