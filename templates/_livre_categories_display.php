<?php
/**
 * Affichage des catégories d’un livre.
 *
 * @var array<string, mixed>|null $book
 * @var list<string>|null $categoryList
 * @var string $labelPrefix
 */
$labelPrefix = trim((string) ($labelPrefix ?? 'Catégories'));

if (isset($book) && is_array($book)) {
    $categoryList = Moncine\LivreCategory::listForBook($book);
} elseif (!isset($categoryList)) {
    $categoryList = [];
}

if ($categoryList === []) {
    return;
}

$showLabel = $labelPrefix !== '';
?>
<p class="magazine-series-categories-display hint">
    <?php if ($showLabel): ?><?= Moncine\View::escape($labelPrefix) ?> : <?php endif; ?>
    <?php foreach ($categoryList as $categoryLabel): ?>
        <span class="magazine-tag magazine-tag--series-category"><?= Moncine\View::escape($categoryLabel) ?></span>
    <?php endforeach; ?>
</p>
