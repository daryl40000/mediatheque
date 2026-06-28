<?php
/**
 * Jeux d’une saga — vue vignettes.
 *
 * @var list<array<string, mixed>> $games
 */
?>
<ul class="collection-grid collection-grid--games" role="list">
    <?php foreach ($games as $game):
        $bibId = (int) ($game['id'] ?? 0);
        $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
        $gameUrl = Moncine\View::gameUrl($bibId);
        $annee = (int) ($game['annee'] ?? 0);
        $platformShort = (string) ($game['platform_short'] ?? '');
        $displayTitle = (string) ($game['display_titre'] ?? $game['titre'] ?? '');
        ?>
        <li class="collection-grid__item" role="listitem">
            <article class="collection-grid__card">
                <a href="<?= Moncine\View::escape($gameUrl) ?>" class="collection-grid__link">
                    <div class="collection-grid__poster-wrap">
                        <?php if ($posterSrc !== ''): ?>
                            <img class="collection-grid__poster" src="<?= $posterSrc ?>"
                                 alt="Jaquette de <?= Moncine\View::escape($displayTitle) ?>"
                                 width="140" height="210" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="collection-grid__poster collection-grid__poster--empty"
                                  aria-hidden="true"></span>
                        <?php endif; ?>
                    </div>
                    <div class="collection-grid__caption">
                        <h3 class="collection-grid__title collection-grid__title--game">
                            <?= Moncine\View::escape($displayTitle) ?>
                        </h3>
                        <p class="collection-grid__meta">
                            <?php if ($platformShort !== ''): ?>
                                <span class="magazine-tag magazine-tag--game-platform"><?= Moncine\View::escape($platformShort) ?></span>
                            <?php endif; ?>
                            <?php if ($annee > 0): ?>
                                <span class="collection-grid__year"><?= $annee ?></span>
                            <?php endif; ?>
                        </p>
                        <?php if (trim((string) ($game['studio'] ?? '')) !== ''): ?>
                            <p class="collection-grid__meta hint"><?= Moncine\View::escape((string) $game['studio']) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            </article>
        </li>
    <?php endforeach; ?>
</ul>
