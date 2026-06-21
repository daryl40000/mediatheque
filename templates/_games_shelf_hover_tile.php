<?php
/**
 * Tuile vignette affichée au survol d’une tranche (vue bibliothèque).
 *
 * @var array<string, mixed> $game
 * @var string $gameUrl
 * @var string $posterSrc
 * @var string $displayTitle
 * @var int $annee
 * @var string $platformShort
 */
$annee = (int) ($annee ?? 0);
$platformShort = (string) ($platformShort ?? '');
$posterSrc = (string) ($posterSrc ?? '');
?>
<div class="game-shelf__preview" aria-hidden="true">
    <article class="collection-grid__card game-shelf__preview-card">
        <a href="<?= Moncine\View::escape($gameUrl) ?>" class="collection-grid__link" tabindex="-1">
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
                    <span><?= Moncine\View::escape($displayTitle) ?></span>
                    <?php if ((string) ($game['linux_badge'] ?? '') !== '' || !empty($game['tested_on_linux']) || !empty($game['linux_not_supported'])): ?>
                        <span class="collection-grid__linux-badge">
                            <?php
                            $size = 'sm';
                            $plain = true;
                            require MONCINE_ROOT . '/templates/_game_linux_badge_if_set.php';
                            ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <p class="collection-grid__meta">
                    <?php if ($platformShort !== ''): ?>
                        <span class="magazine-tag magazine-tag--game-platform"><?= Moncine\View::escape($platformShort) ?></span>
                    <?php endif; ?>
                    <?php if ($annee > 0): ?>
                        <span class="collection-grid__year"><?= $annee ?></span>
                    <?php endif; ?>
                </p>
                <?php
                $iconKeys = $game['edition_icon_keys'] ?? Moncine\GameEditionIcons::iconKeys($game);
                $supplementalText = Moncine\GameEditionIcons::supplementalText($game);
                if ($iconKeys !== [] || $supplementalText !== ''):
                    ?>
                    <p class="collection-grid__meta collection-grid__meta--game-editions">
                        <?php require MONCINE_ROOT . '/templates/_game_edition_icons.php'; ?>
                    </p>
                <?php endif; ?>
                <div class="collection-grid__ratings">
                    <?php
                    $film = $game;
                    $showFoyerAverage = true;
                    $layout = 'stacked';
                    ob_start();
                    require MONCINE_ROOT . '/templates/_film_ratings.php';
                    $ratingsHtml = trim((string) ob_get_clean());
                    if ($ratingsHtml !== '' && $ratingsHtml !== '—') {
                        echo $ratingsHtml;
                    }
                    ?>
                </div>
            </div>
        </a>
    </article>
</div>
