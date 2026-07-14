<?php
/**
 * Filtre latéral par catégorie de série (page Mes magazines).
 *
 * @var list<array{key: string, label: string, count: int}> $categoryFilterChoices
 */
$categoryFilterChoices = $categoryFilterChoices ?? [];
if ($categoryFilterChoices === []) {
    return;
}
?>
<aside class="magazine-category-rail" data-magazine-category-filter aria-label="Filtrer par catégorie">
    <h2 class="magazine-category-rail__title">Catégories</h2>
    <div class="magazine-category-rail__list" role="group" aria-label="Catégories de revues">
        <button type="button"
                class="magazine-tag magazine-tag--series-category magazine-category-rail__btn is-active"
                data-category-filter=""
                aria-pressed="true">
            Toutes
        </button>
        <?php foreach ($categoryFilterChoices as $choice): ?>
            <?php
            $filterKey = (string) ($choice['key'] ?? '');
            $filterLabel = (string) ($choice['label'] ?? '');
            $filterCount = (int) ($choice['count'] ?? 0);
            if ($filterKey === '' || $filterLabel === '' || $filterCount <= 0) {
                continue;
            }
            ?>
            <button type="button"
                    class="magazine-tag magazine-tag--series-category magazine-category-rail__btn"
                    data-category-filter="<?= Moncine\View::escape($filterKey) ?>"
                    aria-pressed="false">
                <?= Moncine\View::escape($filterLabel) ?>
                <span class="magazine-category-rail__count">(<?= $filterCount ?>)</span>
            </button>
        <?php endforeach; ?>
    </div>
</aside>
