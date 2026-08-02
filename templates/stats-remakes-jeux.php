<?php
/**
 * Liste remake / jeu d’origine avec jaquettes (couleur si possédé, grisé sinon).
 *
 * @var list<array{remake: array<string, mixed>, original: ?array<string, mixed>}> $remakePairs
 */
$remakePairs = $remakePairs ?? [];
$pairCount = count($remakePairs);

/**
 * Affiche une jaquette cliquable (réutilise le style des jeux liés).
 *
 * @param array{titre?: string, annee?: int, poster_url?: mixed, in_library?: bool, url?: string} $card
 * @param string $roleLabel Libellé sous la jaquette (Remake / Jeu d’origine)
 */
$renderRemakePoster = static function (array $card, string $roleLabel): void {
    $posterSrc = Moncine\View::posterSrc($card['poster_url'] ?? null);
    $url = trim((string) ($card['url'] ?? ''));
    $annee = (int) ($card['annee'] ?? 0);
    $titre = (string) ($card['titre'] ?? '');
    $inLibrary = !empty($card['in_library']);
    $itemClasses = 'game-related-posters__item';
    if (!$inLibrary) {
        $itemClasses .= ' game-related-posters__item--missing';
    }
    ?>
    <div class="stats-remake-pair__side">
        <p class="stats-remake-pair__role"><?= Moncine\View::escape($roleLabel) ?></p>
        <ul class="game-related-posters" role="list">
            <li class="<?= $itemClasses ?>" role="listitem">
                <?php if ($url !== ''): ?>
                    <a href="<?= Moncine\View::escape($url) ?>"
                       class="game-related-posters__link"
                       title="<?= Moncine\View::escape($titre) ?>">
                <?php else: ?>
                    <span class="game-related-posters__link game-related-posters__link--static"
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
                <?php if ($url !== ''): ?>
                    </a>
                <?php else: ?>
                    </span>
                <?php endif; ?>
            </li>
        </ul>
        <p class="stats-remake-pair__title">
            <?php if ($url !== ''): ?>
                <a href="<?= Moncine\View::escape($url) ?>"><?= Moncine\View::escape($titre) ?></a>
            <?php else: ?>
                <?= Moncine\View::escape($titre) ?>
            <?php endif; ?>
        </p>
        <p class="stats-remake-pair__possession<?= $inLibrary ? '' : ' stats-remake-pair__possession--missing' ?>">
            <?= $inLibrary ? 'Possédé' : 'Non possédé' ?>
        </p>
    </div>
    <?php
};
?>
<section class="stats-page">
    <p class="stats-page__back">
        <a href="/statistiques.php">← Retour aux statistiques</a>
    </p>
    <h1>Remakes</h1>
    <p class="lead">
        <?= $pairCount ?> remake<?= $pairCount > 1 ? 's' : '' ?> lié<?= $pairCount > 1 ? 's' : '' ?>
        à votre collection. Chaque ligne montre le remake et son jeu d’origine :
        jaquette en couleur si vous le possédez, grisée sinon. Cliquez pour ouvrir la fiche.
    </p>

    <?php if ($pairCount === 0): ?>
        <p class="hint">
            Aucun remake pour l’instant. Marquez une fiche comme remake et choisissez le jeu d’origine
            dans le catalogue pour la voir apparaître ici.
        </p>
    <?php else: ?>
        <ul class="stats-remake-pairs" role="list">
            <?php foreach ($remakePairs as $pair): ?>
                <?php
                if (!is_array($pair)) {
                    continue;
                }
                $remake = is_array($pair['remake'] ?? null) ? $pair['remake'] : null;
                $original = is_array($pair['original'] ?? null) ? $pair['original'] : null;
                if ($remake === null) {
                    continue;
                }
                ?>
                <li class="stats-remake-pair" role="listitem">
                    <?php $renderRemakePoster($remake, 'Remake'); ?>
                    <span class="stats-remake-pair__arrow" aria-hidden="true">↔</span>
                    <?php if ($original !== null): ?>
                        <?php $renderRemakePoster($original, 'Jeu d’origine'); ?>
                    <?php else: ?>
                        <div class="stats-remake-pair__side stats-remake-pair__side--empty">
                            <p class="stats-remake-pair__role">Jeu d’origine</p>
                            <p class="hint">Non renseigné</p>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
