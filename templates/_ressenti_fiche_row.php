<?php
/**
 * Ressenti personnel + popover discret foyer / amis (fiche film, jeu, BD).
 *
 * @var int|null $monRessenti
 * @var array{foyer: list<array<string, mixed>>, friends: list<array<string, mixed>>} $socialRessentis
 * @var bool $isWishlist
 */
$isWishlist = $isWishlist ?? false;
$socialRessentis = $socialRessentis ?? ['foyer' => [], 'friends' => []];
$hasOwn = !empty($monRessenti);

$othersFoyer = [];
$othersFriends = [];
if (!$isWishlist) {
    foreach ($socialRessentis['foyer'] ?? [] as $entry) {
        if (!empty($entry['is_self'])) {
            continue;
        }
        $score = isset($entry['ressenti_score']) ? Moncine\RessentiNote::normalizeScore((int) $entry['ressenti_score']) : null;
        if ($score === null) {
            continue;
        }
        $othersFoyer[] = $entry;
    }
    foreach ($socialRessentis['friends'] ?? [] as $entry) {
        if (!empty($entry['is_self'])) {
            continue;
        }
        $score = isset($entry['ressenti_score']) ? Moncine\RessentiNote::normalizeScore((int) $entry['ressenti_score']) : null;
        if ($score === null) {
            continue;
        }
        $othersFriends[] = $entry;
    }
}

$othersCount = count($othersFoyer) + count($othersFriends);
if (!$hasOwn && $othersCount === 0) {
    return;
}

$renderOthersList = static function (array $entries): void {
    echo '<ul class="ressenti-social-list">';
    foreach ($entries as $entry) {
        $name = (string) ($entry['display_name'] ?? '');
        $score = isset($entry['ressenti_score']) ? (int) $entry['ressenti_score'] : null;
        echo '<li class="ressenti-social-list__item">';
        echo '<span class="ressenti-social-list__name">';
        echo Moncine\View::escape($name !== '' ? $name : 'Membre');
        echo '</span>';
        echo '<span class="ressenti-social-list__badge">';
        $showLabel = false;
        $size = 'small';
        require MONCINE_ROOT . '/templates/_ressenti_badge.php';
        echo '</span>';
        echo '</li>';
    }
    echo '</ul>';
};
?>
<p class="film-detail__ressenti">
    <?php if ($hasOwn): ?>
        <?php
        $score = (int) $monRessenti;
        $showLabel = true;
        $size = 'default';
        require MONCINE_ROOT . '/templates/_ressenti_badge.php';
        ?>
    <?php endif; ?>

    <?php if ($othersCount > 0): ?>
        <details class="ressenti-social-popover">
            <summary class="ressenti-social-popover__trigger">
                Foyer et amis
                <span class="ressenti-social-popover__count">(<?= (int) $othersCount ?>)</span>
            </summary>
            <div class="ressenti-social-popover__panel">
                <p class="ressenti-social-popover__hint">
                    Ressentis partagés sur cette œuvre (hors le vôtre).
                </p>
                <?php if ($othersFoyer !== []): ?>
                    <p class="ressenti-social-popover__group">Foyer</p>
                    <?php $renderOthersList($othersFoyer); ?>
                <?php endif; ?>
                <?php if ($othersFriends !== []): ?>
                    <p class="ressenti-social-popover__group">Amis</p>
                    <?php $renderOthersList($othersFriends); ?>
                <?php endif; ?>
            </div>
        </details>
    <?php endif; ?>
</p>
