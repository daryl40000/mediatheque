<?php
/**
 * Tableau imprimable — liste de séries (BD ou magazines).
 *
 * @var list<array<string, mixed>> $seriesList
 * @var bool $isWishlist
 * @var string $countColumnLabel
 * @var string $domain 'bd'|'magazine'
 */
$seriesList = $seriesList ?? [];
$isWishlist = !empty($isWishlist);
$countColumnLabel = (string) ($countColumnLabel ?? 'Possédés / catalogue');
$domain = (string) ($domain ?? 'bd');
?>
<table class="print-table print-table--series">
    <thead>
        <tr>
            <th scope="col">Série</th>
            <th scope="col"><?= $domain === 'bd' ? 'Type' : 'Éditeur' ?></th>
            <?php if ($domain === 'bd'): ?>
                <th scope="col">Éditeur</th>
            <?php endif; ?>
            <th scope="col"><?= Moncine\View::escape($countColumnLabel) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($seriesList as $series): ?>
            <?php
            $titre = (string) ($series['titre'] ?? '');
            $editeur = trim((string) ($series['editeur'] ?? ''));
            if ($domain === 'bd') {
                $kindLabel = (string) ($series['kind_label'] ?? '');
                $owned = (int) ($series['possessed_tome_count'] ?? $series['tome_count'] ?? 0);
                $catalog = (int) ($series['catalog_tome_count'] ?? 0);
                $countCell = $isWishlist
                    ? (string) $owned
                    : $owned . ' / ' . $catalog;
            } else {
                $owned = (int) ($series['issue_count'] ?? $series['possessed_issue_count'] ?? 0);
                $catalog = (int) ($series['catalog_issue_count'] ?? 0);
                $countCell = $isWishlist
                    ? (string) $owned
                    : ($catalog > 0 ? $owned . ' / ' . $catalog : (string) $owned);
            }
            ?>
            <tr>
                <td><?= Moncine\View::escape($titre) ?></td>
                <?php if ($domain === 'bd'): ?>
                    <td><?= Moncine\View::escape($kindLabel) ?></td>
                    <td><?= Moncine\View::escape($editeur) ?></td>
                <?php else: ?>
                    <td><?= Moncine\View::escape($editeur) ?></td>
                <?php endif; ?>
                <td><?= Moncine\View::escape($countCell) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
