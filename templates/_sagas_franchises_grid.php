<?php
/**
 * Toutes les sagas jeux — vue vignettes.
 *
 * @var list<array{franchise: string, game_count: int, poster_url: string}> $franchises
 * @var string $viewMode
 */
?>
<ul class="collection-grid collection-grid--games sagas-franchises-grid" role="list">
    <?php foreach ($franchises as $item):
        $franchiseName = (string) ($item['franchise'] ?? '');
        $posterSrc = Moncine\View::posterSrc(
            trim((string) ($item['poster_url'] ?? '')) !== ''
                ? (string) $item['poster_url']
                : null
        );
        $franchiseUrl = Moncine\View::gameFranchiseUrl($franchiseName, $viewMode);
        $gameCount = (int) ($item['game_count'] ?? 0);
        ?>
        <li class="collection-grid__item" role="listitem">
            <article class="collection-grid__card">
                <a href="<?= Moncine\View::escape($franchiseUrl) ?>" class="collection-grid__link">
                    <div class="collection-grid__poster-wrap">
                        <?php if ($posterSrc !== ''): ?>
                            <img class="collection-grid__poster" src="<?= $posterSrc ?>"
                                 alt="Jaquette de la saga <?= Moncine\View::escape($franchiseName) ?>"
                                 width="140" height="210" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="collection-grid__poster collection-grid__poster--empty"
                                  aria-hidden="true"></span>
                        <?php endif; ?>
                    </div>
                    <div class="collection-grid__caption">
                        <h3 class="collection-grid__title collection-grid__title--game">
                            <?= Moncine\View::escape($franchiseName) ?>
                        </h3>
                        <p class="collection-grid__meta">
                            <span class="hint">
                                <?= $gameCount ?> jeu<?= $gameCount > 1 ? 'x' : '' ?>
                            </span>
                        </p>
                    </div>
                </a>
            </article>
        </li>
    <?php endforeach; ?>
</ul>
