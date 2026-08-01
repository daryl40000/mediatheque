<?php
/**
 * Bandeau discret : affiches + année pour jeux liés (extensions, remakes, saga…).
 *
 * @var list<array{title: string, layout?: string, items: list<array{url: string, poster_url: mixed, annee: int, titre: string, in_library?: bool, is_current?: bool}>}> $gameRelatedSections
 */
$gameRelatedSections = array_values(array_filter(
    $gameRelatedSections ?? [],
    static fn (array $section): bool => ($section['items'] ?? []) !== []
));
if ($gameRelatedSections === []) {
    return;
}
?>
<div class="game-related-layout">
    <?php foreach ($gameRelatedSections as $section): ?>
        <?php
        $layout = (string) ($section['layout'] ?? 'compact');
        $colClass = 'game-related-col';
        if ($layout === 'wide') {
            $colClass .= ' game-related-col--wide';
        }
        $sectionItems = $section['items'] ?? [];
        $hasCurrent = false;
        foreach ($sectionItems as $probe) {
            if (!empty($probe['is_current'])) {
                $hasCurrent = true;
                break;
            }
        }
        $stripClass = 'game-related-posters';
        if ($hasCurrent) {
            $stripClass .= ' bd-series-context__strip';
        }
        ?>
        <section class="<?= $colClass ?><?= $hasCurrent ? ' bd-series-context' : '' ?>">
            <h2 class="game-related-col__title"><?= Moncine\View::escape((string) ($section['title'] ?? '')) ?></h2>
            <ul class="<?= $stripClass ?>" role="list">
                <?php foreach ($sectionItems as $item): ?>
                    <?php
                    if (!is_array($item)) {
                        continue;
                    }
                    $posterSrc = Moncine\View::posterSrc($item['poster_url'] ?? null);
                    $url = trim((string) ($item['url'] ?? ''));
                    $annee = (int) ($item['annee'] ?? 0);
                    $titre = (string) ($item['titre'] ?? '');
                    $inLibrary = !empty($item['in_library']);
                    $isCurrent = !empty($item['is_current']);
                    $itemClasses = 'game-related-posters__item';
                    if ($isCurrent) {
                        $itemClasses .= ' bd-series-context__item--current';
                    }
                    if (!$inLibrary) {
                        $itemClasses .= ' game-related-posters__item--missing';
                        if ($hasCurrent) {
                            $itemClasses .= ' bd-series-context__item--unowned';
                        }
                    }
                    ?>
                    <li class="<?= $itemClasses ?>" role="listitem">
                        <?php if ($url !== '' && !$isCurrent): ?>
                            <a href="<?= Moncine\View::escape($url) ?>"
                               class="game-related-posters__link"
                               title="<?= Moncine\View::escape($titre) ?>">
                        <?php else: ?>
                            <span class="game-related-posters__link<?= $isCurrent ? ' bd-series-context__link--current' : ' game-related-posters__link--static' ?>"
                                  <?php if ($isCurrent): ?>aria-current="page"<?php endif; ?>
                                  title="<?= Moncine\View::escape($titre) ?>">
                        <?php endif; ?>
                            <?php if ($posterSrc !== ''): ?>
                                <img class="game-related-posters__poster"
                                     src="<?= $posterSrc ?>"
                                     alt=""
                                     loading="lazy">
                            <?php else: ?>
                                <span class="game-related-posters__placeholder" aria-hidden="true">🎮</span>
                            <?php endif; ?>
                            <?php if ($annee > 0): ?>
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
    <?php endforeach; ?>
</div>
