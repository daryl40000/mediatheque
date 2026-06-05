<?php
/**
 * Onglets média sur le profil public d’un utilisateur (indépendants de la session).
 *
 * @var int $targetUserId
 * @var string $profileDomain
 */
use Moncine\MediaDomain;

$profileDomain = MediaDomain::normalize($profileDomain ?? MediaDomain::FILM);
?>
<nav class="media-domain-tabs media-domain-tabs--profile" aria-label="Type de média du profil">
    <?php foreach (MediaDomain::choices() as $domainKey => $domainLabel): ?>
        <?php
        $isActive = $domainKey === $profileDomain;
        $tabTheme = MediaDomain::theme($domainKey);
        $tabUrl = Moncine\View::userProfileUrl($targetUserId, $domainKey);
        $tabStyle = '--tab-accent: ' . $tabTheme['accent']
            . '; --tab-accent-muted: ' . $tabTheme['accent_muted']
            . '; --tab-accent-hover: ' . $tabTheme['accent_hover'];
        ?>
        <a href="<?= Moncine\View::escape($tabUrl) ?>"
           class="media-domain-tabs__tab media-domain-tabs__tab--<?= Moncine\View::escape($domainKey) ?><?= $isActive ? ' media-domain-tabs__tab--active' : '' ?>"
           style="<?= Moncine\View::escape($tabStyle) ?>"
           <?= $isActive ? ' aria-current="true"' : '' ?>>
            <span class="media-domain-tabs__dot" aria-hidden="true"></span>
            <?= Moncine\View::escape($domainLabel) ?>
        </a>
    <?php endforeach; ?>
</nav>
