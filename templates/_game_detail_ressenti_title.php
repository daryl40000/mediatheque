<?php
/**
 * Ressenti compact à droite du titre (icône seule + crayon de modification).
 *
 * @var int $gameId
 * @var int|null $monRessenti
 * @var array{foyer: list<array<string, mixed>>, friends: list<array<string, mixed>>} $socialRessentis
 * @var bool $isWishlist
 */
$gameId = (int) ($gameId ?? 0);
$isWishlist = $isWishlist ?? false;
$socialRessentis = $socialRessentis ?? ['foyer' => [], 'friends' => []];
$hasRessenti = !empty($monRessenti);

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

if (!$hasRessenti && $othersCount === 0) {
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
<div class="game-detail__title-ressenti">
    <?php if ($hasRessenti): ?>
        <div class="game-detail__title-ressenti-main">
            <?php
            $score = (int) $monRessenti;
            $showLabel = false;
            $size = 'default';
            require MONCINE_ROOT . '/templates/_ressenti_badge.php';
            ?>
            <details class="game-detail-ressenti__edit">
                <summary class="game-detail-ressenti__edit-trigger" aria-label="Modifier mon ressenti" title="Modifier mon ressenti">
                    <svg class="icon-edit" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 20h9"/>
                        <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                    </svg>
                </summary>
                <div class="game-detail-ressenti__edit-form game-detail-ressenti__edit-popover">
                    <?php
                    $defaultNote = (int) $monRessenti;
                    require MONCINE_ROOT . '/templates/_marquer_joue_form.php';
                    ?>
                </div>
            </details>
        </div>
    <?php endif; ?>

    <?php if ($othersCount > 0): ?>
        <details class="ressenti-social-popover ressenti-social-popover--compact">
            <summary class="ressenti-social-popover__trigger"
                     aria-label="Ressentis du foyer et des amis (<?= (int) $othersCount ?>)"
                     title="Ressentis du foyer et des amis">
                <span class="ressenti-social-popover__count"><?= (int) $othersCount ?></span>
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
</div>
