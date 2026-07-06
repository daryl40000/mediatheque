<?php
/**
 * Bandeau série BD : tomes voisins avec le tome courant encadré au centre.
 *
 * @var list<array{url: string, poster_url: mixed, annee: int, titre: string, is_possessed: bool, is_current: bool, tome_label: string}> $bdSeriesNeighbors
 * @var string $seriesTitre
 * @var bool $isWishlist
 */
$bdSeriesNeighbors = $bdSeriesNeighbors ?? [];
$seriesTitre = trim((string) ($seriesTitre ?? ''));
$isWishlist = $isWishlist ?? false;
if ($bdSeriesNeighbors === []) {
    return;
}
?>
<section class="game-detail__related bd-series-context" aria-label="Tomes de la série">
    <h2 class="game-detail__section-title">Dans la série<?= $seriesTitre !== '' ? ' « ' . Moncine\View::escape($seriesTitre) . ' »' : '' ?></h2>
    <ul class="game-related-posters bd-series-context__strip" role="list">
        <?php foreach ($bdSeriesNeighbors as $item): ?>
            <?php
            $posterSrc = Moncine\View::posterSrc($item['poster_url'] ?? null);
            $url = trim((string) ($item['url'] ?? ''));
            $annee = (int) ($item['annee'] ?? 0);
            $titre = (string) ($item['titre'] ?? '');
            $tomeLabel = trim((string) ($item['tome_label'] ?? ''));
            $isPossessed = !empty($item['is_possessed']);
            $isCurrent = !empty($item['is_current']);
            $itemClasses = 'game-related-posters__item';
            if ($isCurrent) {
                $itemClasses .= ' bd-series-context__item--current';
            }
            if (!$isWishlist && !$isPossessed) {
                $itemClasses .= ' game-related-posters__item--missing bd-series-context__item--unowned';
            }
            ?>
            <li class="<?= $itemClasses ?>" role="listitem">
                <?php if ($url !== '' && !$isCurrent): ?>
                    <a href="<?= Moncine\View::escape($url) ?>"
                       class="game-related-posters__link"
                       title="<?= Moncine\View::escape($titre) ?>">
                <?php else: ?>
                    <span class="game-related-posters__link<?= $isCurrent ? ' bd-series-context__link--current' : ' game-related-posters__link--static' ?>"
                          <?php if ($isCurrent): ?>aria-current="page"<?php endif; ?>>
                <?php endif; ?>
                    <?php if ($posterSrc !== ''): ?>
                        <img class="game-related-posters__poster"
                             src="<?= $posterSrc ?>"
                             alt=""
                             loading="lazy">
                    <?php else: ?>
                        <span class="game-related-posters__placeholder" aria-hidden="true">📚</span>
                    <?php endif; ?>
                    <?php if ($tomeLabel !== ''): ?>
                        <span class="bd-series-context__tome"><?= Moncine\View::escape($tomeLabel) ?></span>
                    <?php elseif ($annee > 0): ?>
                        <span class="game-related-posters__year"><?= $annee ?></span>
                    <?php endif; ?>
                <?php if ($url !== '' && !$isCurrent): ?>
                    </a>
                <?php else: ?>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
