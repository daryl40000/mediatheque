<?php
/**
 * Onglets de changement de domaine média (Films, BD, Livres…).
 * Chaque onglet a sa propre couleur ; l’actif pilote le thème global.
 *
 * @var string $currentPath
 */
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;

$activeDomain = MediaContext::current();
?>
<nav class="media-domain-tabs" aria-label="Type de média">
    <?php foreach (MediaDomain::choices() as $domainKey => $domainLabel): ?>
        <?php
        $isActive = $domainKey === $activeDomain;
        $tabTheme = MediaDomain::theme($domainKey);
        $tabRedirect = MediaDomainGuards::redirectTargetForTabSwitch(
            $domainKey,
            $currentPath !== '' ? $currentPath : '/films.php',
            (string) ($_SERVER['QUERY_STRING'] ?? '')
        );
        $tabUrl = '/set-media-domain.php?domain=' . rawurlencode($domainKey)
            . '&redirect=' . rawurlencode($tabRedirect);
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
