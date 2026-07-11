<?php
/**
 * Titre de section (h1–h3) avec bulle d’aide optionnelle.
 *
 * @var string $title
 * @var string $tag        h1 | h2 | h3 (défaut h2)
 * @var string|null $class classes CSS additionnelles
 * @var string $info
 * @var string|null $infoHtml
 * @var string|null $infoAria
 */
$title = (string) ($title ?? '');
$tag = (string) ($tag ?? 'h2');
if (!in_array($tag, ['h1', 'h2', 'h3'], true)) {
    $tag = 'h2';
}
$class = trim((string) ($class ?? ''));
$info = trim((string) ($info ?? ''));
$infoHtml = $infoHtml ?? null;
$infoAria = trim((string) ($infoAria ?? $title));
$hasInfo = $infoHtml !== null && $infoHtml !== '' || $info !== '';
$headingClass = 'section-heading-with-info' . ($class !== '' ? ' ' . $class : '');
?>
<?php if (!$hasInfo): ?>
    <<?= $tag ?><?= $class !== '' ? ' class="' . Moncine\View::escape($class) . '"' : '' ?>><?= Moncine\View::escape($title) ?></<?= $tag ?>>
<?php else: ?>
    <<?= $tag ?> class="<?= Moncine\View::escape($headingClass) ?>">
        <span class="section-heading-with-info__text"><?= Moncine\View::escape($title) ?></span>
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
    </<?= $tag ?>>
<?php endif; ?>
