<?php
/**
 * Ressentis du foyer et des amis sur la même œuvre catalogue.
 *
 * @var array{foyer: list<array<string, mixed>>, friends: list<array<string, mixed>>} $socialRessentis
 */
$socialRessentis = $socialRessentis ?? ['foyer' => [], 'friends' => []];
$foyer = $socialRessentis['foyer'] ?? [];
$friends = $socialRessentis['friends'] ?? [];

$hasFoyer = $foyer !== [];
$hasFriends = $friends !== [];

if (!$hasFoyer && !$hasFriends) {
    return;
}

$renderList = static function (array $entries): void {
    if ($entries === []) {
        echo '<p class="hint">Aucun ressenti enregistré pour l’instant.</p>';
        return;
    }
    echo '<ul class="ressenti-social-list">';
    foreach ($entries as $entry) {
        $name = (string) ($entry['display_name'] ?? '');
        $score = isset($entry['ressenti_score']) ? (int) $entry['ressenti_score'] : null;
        $isSelf = !empty($entry['is_self']);
        echo '<li class="ressenti-social-list__item' . ($isSelf ? ' ressenti-social-list__item--self' : '') . '">';
        echo '<span class="ressenti-social-list__name">';
        if ($isSelf) {
            echo 'Vous';
        } else {
            echo Moncine\View::escape($name !== '' ? $name : 'Membre');
        }
        echo '</span>';
        echo '<span class="ressenti-social-list__badge">';
        $scoreVar = $score;
        $showLabel = false;
        $size = 'small';
        require MONCINE_ROOT . '/templates/_ressenti_badge.php';
        echo '</span>';
        echo '</li>';
    }
    echo '</ul>';
};
?>
<section class="ressenti-social-panel">
    <h2 class="ressenti-social-panel__title">Ressentis autour de cette œuvre</h2>
    <p class="hint">Membres de votre foyer et amis qui ont partagé un ressenti sur cette œuvre (même s’ils ne l’ont pas dans leur bibliothèque).</p>

    <?php if ($hasFoyer): ?>
        <h3 class="ressenti-social-panel__subtitle">Foyer</h3>
        <?php $renderList($foyer); ?>
    <?php endif; ?>

    <?php if ($hasFriends): ?>
        <h3 class="ressenti-social-panel__subtitle">Amis</h3>
        <?php $renderList($friends); ?>
    <?php endif; ?>
</section>
