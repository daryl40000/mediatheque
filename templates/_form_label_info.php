<?php
/**
 * Libellé de formulaire avec bulle d’aide optionnelle (icône « i »).
 *
 * @var string $for       id du champ associé
 * @var string $label     texte du libellé
 * @var string $info      texte de la bulle (échappé automatiquement)
 * @var string|null $infoHtml HTML sûr pour la bulle (liens…) — prioritaire sur $info
 * @var string|null $infoAria libellé accessibilité du bouton d’aide
 */
$for = (string) ($for ?? '');
$label = (string) ($label ?? '');
$info = trim((string) ($info ?? ''));
$infoHtml = $infoHtml ?? null;
$infoAria = trim((string) ($infoAria ?? $label));
$hasInfo = $infoHtml !== null && $infoHtml !== '' || $info !== '';
?>
<?php if (!$hasInfo): ?>
    <label for="<?= Moncine\View::escape($for) ?>"><?= Moncine\View::escape($label) ?></label>
<?php else: ?>
    <label class="form-label-with-info" for="<?= Moncine\View::escape($for) ?>">
        <span class="form-label-with-info__text"><?= Moncine\View::escape($label) ?></span>
        <span class="info-tooltip" tabindex="0" aria-label="<?= Moncine\View::escape($infoAria) ?>">
            <span class="info-tooltip__icon" aria-hidden="true">i</span>
            <span class="info-tooltip__popup" role="tooltip">
                <?php if ($infoHtml !== null && $infoHtml !== ''): ?>
                    <?= $infoHtml ?>
                <?php else: ?>
                    <?= Moncine\View::escape($info) ?>
                <?php endif; ?>
            </span>
        </span>
    </label>
<?php endif; ?>
