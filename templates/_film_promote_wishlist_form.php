<?php
/**
 * Formulaire « J’ai acheté » avec choix d’une version recherchée (wishlist_targets).
 *
 * @var int $filmId
 * @var list<array<string, mixed>> $wishlistTargets
 * @var string $formAction
 * @var string $formId
 * @var string $formClass
 * @var bool $includeListContext
 * @var bool $compactPromoteForm Liste Mes envies (ligne compacte)
 * @var list<array{0: string, 1: string}> $extraHiddenFields
 */
$formAction = $formAction ?? '/promouvoir-collection.php';
$formId = $formId ?? '';
$formClass = $formClass ?? 'film-promote-form import-form';
$wishlistTargets = $wishlistTargets ?? [];
$includeListContext = !empty($includeListContext);
$compactPromoteForm = !empty($compactPromoteForm);
$extraHiddenFields = $extraHiddenFields ?? [];
$hasTargets = $wishlistTargets !== [];
$targetSelectId = 'promote_target_' . (int) $filmId;
$supportSelectId = 'promote_support_' . (int) $filmId;
?>
<form method="post" action="<?= Moncine\View::escape($formAction) ?>"
      class="<?= Moncine\View::escape($formClass) ?><?= $compactPromoteForm ? ' wishlist-promote-form--compact' : '' ?>"
      <?= $formId !== '' ? ' id="' . Moncine\View::escape($formId) . '"' : '' ?>>
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="film_id" value="<?= (int) $filmId ?>">
    <?php foreach ($extraHiddenFields as [$name, $value]): ?>
        <input type="hidden" name="<?= Moncine\View::escape($name) ?>" value="<?= Moncine\View::escape($value) ?>">
    <?php endforeach; ?>
    <?php if ($includeListContext && isset($filmListContext)): ?>
        <?php require MONCINE_ROOT . '/templates/_film_list_context_fields.php'; ?>
    <?php endif; ?>

    <?php if ($hasTargets): ?>
        <?php if (!$compactPromoteForm): ?>
            <label for="<?= Moncine\View::escape($targetSelectId) ?>">Version achetée</label>
        <?php endif; ?>
        <select name="wishlist_target_id" id="<?= Moncine\View::escape($targetSelectId) ?>"
                class="film-promote-form__target-select"
                <?= $compactPromoteForm ? ' aria-label="Version achetée" title="Version achetée"' : '' ?>>
            <?php foreach ($wishlistTargets as $row):
                $targetId = (int) ($row['id'] ?? 0);
                $supportLabel = Moncine\SupportPhysique::label((string) ($row['support_physique'] ?? ''));
                $ean = Moncine\OeuvreEanRepository::normalizeEan((string) ($row['ean'] ?? ''));
                $note = trim((string) ($row['label'] ?? ''));
                $optionLabel = $supportLabel;
                if ($ean !== '') {
                    $optionLabel .= ' — EAN ' . Moncine\View::formatEan($ean);
                }
                if ($note !== '') {
                    $optionLabel .= ' (' . $note . ')';
                }
                ?>
                <option value="<?= $targetId ?>"><?= Moncine\View::escape($optionLabel) ?></option>
            <?php endforeach; ?>
            <option value="0">Autre support…</option>
        </select>
    <?php endif; ?>

    <?php if (!$compactPromoteForm): ?>
        <label for="<?= Moncine\View::escape($supportSelectId) ?>">Support physique<?= $hasTargets ? ' (si autre version)' : '' ?></label>
    <?php endif; ?>
    <select name="support_physique" id="<?= Moncine\View::escape($supportSelectId) ?>"
            <?= $compactPromoteForm ? ' aria-label="Support physique" title="Support (si autre version)"' : '' ?>>
        <option value="">— Plus tard —</option>
        <?php foreach (Moncine\SupportPhysique::choices() as $key => $label): ?>
            <option value="<?= Moncine\View::escape($key) ?>"><?= Moncine\View::escape($label) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary<?= $formClass !== 'film-promote-form import-form' ? ' btn-sm' : '' ?>">
        <?= $hasTargets ? 'J’ai acheté' : 'J’ai acheté ce film' ?>
    </button>
</form>
