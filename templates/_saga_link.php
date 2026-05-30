<?php
/**
 * Lien vers la page d’une saga.
 *
 * @var string $sagaName
 * @var int|null $sagaOrdre numéro optionnel affiché avant le nom
 */
$sagaName = trim((string) ($sagaName ?? ''));
$sagaOrdre = isset($sagaOrdre) ? (int) $sagaOrdre : 0;
if ($sagaName === ''): ?>
    —
<?php else: ?>
    <?php if ($sagaOrdre > 0): ?>
        <span class="saga-ordre"><?= (int) $sagaOrdre ?>.</span>
    <?php endif; ?>
    <a href="<?= Moncine\View::escape(Moncine\View::sagaUrl($sagaName)) ?>"
       class="saga-link"
       title="Voir tous les films de la saga « <?= Moncine\View::escape($sagaName) ?> »"><?= Moncine\View::escape($sagaName) ?></a>
<?php endif; ?>
