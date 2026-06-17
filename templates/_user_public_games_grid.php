<?php
/**
 * Grille lecture seule des jeux d’un autre utilisateur.
 *
 * @var list<array<string, mixed>> $games
 * @var int $targetUserId
 * @var string $listMode
 * @var string $sortBy
 * @var string $sortDir
 * @var string $profileDomain
 */
$sortLink = static function (string $label, string $column) use ($targetUserId, $listMode, $sortBy, $sortDir, $profileDomain): void {
    $active = $sortBy === $column;
    $domain = $profileDomain ?? Moncine\MediaDomain::JEU;
    ?>
    <a href="<?= Moncine\View::escape(
        Moncine\View::userProfileListUrl($targetUserId, $listMode, $column, $sortBy, $sortDir, null, $domain)
    ) ?>"
       class="collection-grid-sort__link<?= $active ? ' is-active' : '' ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
?>
<?php if ($games === []): ?>
    <p class="hint">Aucun jeu dans cette liste.</p>
<?php else: ?>
    <p class="stats"><?= count($games) ?> jeu<?= count($games) > 1 ? 'x' : '' ?></p>
    <nav class="collection-grid-sort social-profile-list-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $sortLink('Titre', 'titre'); ?>
        <?php $sortLink('Année', 'annee'); ?>
        <?php $sortLink('Studio', 'studio'); ?>
        <?php $sortLink('Plateforme', 'platform'); ?>
    </nav>
    <ul class="collection-grid collection-grid--games social-profile-grid" role="list">
        <?php foreach ($games as $game):
            $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
            $titre = (string) ($game['titre'] ?? '');
            $annee = (int) ($game['annee'] ?? 0);
            $platformShort = (string) ($game['platform_short'] ?? '');
            ?>
            <li class="collection-grid__item" role="listitem">
                <article class="collection-grid__card">
                    <div class="collection-grid__link social-profile-grid__card">
                        <?php if ($posterSrc !== ''): ?>
                            <div class="collection-grid__poster-wrap">
                                <img class="collection-grid__poster" src="<?= $posterSrc ?>"
                                     alt="Jaquette de <?= Moncine\View::escape($titre) ?>"
                                     loading="lazy" decoding="async">
                            </div>
                        <?php else: ?>
                            <div class="collection-grid__poster-wrap collection-grid__poster-wrap--empty">
                                <span class="collection-grid__poster-placeholder">?</span>
                            </div>
                        <?php endif; ?>
                        <div class="collection-grid__meta">
                            <span class="collection-grid__title"><?= Moncine\View::escape($titre) ?></span>
                            <?php if ($platformShort !== ''): ?>
                                <span class="magazine-tag magazine-tag--game-platform"><?= Moncine\View::escape($platformShort) ?></span>
                            <?php endif; ?>
                            <?php if ($annee > 0): ?>
                                <span class="collection-grid__year"><?= $annee ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
