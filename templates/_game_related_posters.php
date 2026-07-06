<?php
/**
 * Bandeau discret : affiches + année pour jeux liés (extensions, remakes…).
 *
 * @var list<array{title: string, items: list<array{url: string, poster_url: mixed, annee: int, titre: string, in_library?: bool}>}> $gameRelatedSections
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
        ?>
        <section class="<?= $colClass ?>">
            <h2 class="game-related-col__title"><?= Moncine\View::escape((string) ($section['title'] ?? '')) ?></h2>
            <ul class="game-related-posters" role="list">
                <?php foreach ($section['items'] as $item): ?>
                    <?php
                    if (!is_array($item)) {
                        continue;
                    }
                    $posterSrc = Moncine\View::posterSrc($item['poster_url'] ?? null);
                    $url = trim((string) ($item['url'] ?? ''));
                    $annee = (int) ($item['annee'] ?? 0);
                    $titre = (string) ($item['titre'] ?? '');
                    $inLibrary = !empty($item['in_library']);
                    ?>
                    <li class="game-related-posters__item<?= $inLibrary ? '' : ' game-related-posters__item--missing' ?>" role="listitem">
                        <?php if ($url !== ''): ?>
                            <a href="<?= Moncine\View::escape($url) ?>"
                               class="game-related-posters__link"
                               title="<?= Moncine\View::escape($titre) ?>">
                        <?php else: ?>
                            <span class="game-related-posters__link game-related-posters__link--static">
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
                        <?php if ($url !== ''): ?>
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
