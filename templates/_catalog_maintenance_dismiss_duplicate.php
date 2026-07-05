<?php
/**
 * Bouton « Conserver les deux fiches » pour un groupe de doublons catalogue.
 *
 * @var string $dismissGroupType
 * @var string $dismissGroupKey
 * @var string $dismissFormSuffix
 */
$dismissFormSuffix = $dismissFormSuffix ?? 'default';
?>
<form method="post" class="catalog-maintenance-dismiss-form import-form">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="action" value="dismiss_duplicate_group">
    <input type="hidden" name="group_type" value="<?= Moncine\View::escape($dismissGroupType) ?>">
    <input type="hidden" name="group_key" value="<?= Moncine\View::escape($dismissGroupKey) ?>">
    <button type="submit" class="btn btn-secondary btn-sm"
            onclick="return confirm('Conserver toutes ces fiches séparément ? Elles ne seront plus signalées comme doublons.');">
        Conserver toutes les fiches
    </button>
</form>
