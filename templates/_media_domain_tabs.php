<?php
/**
 * Onglets de changement de domaine média (Films, BD, Livres…).
 * Chaque onglet a sa propre couleur ; l’actif pilote le thème global.
 * Bascule en POST + CSRF (évite qu’un autre site force le changement d’onglet).
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
        $tabStyle = '--tab-accent: ' . $tabTheme['accent']
            . '; --tab-accent-muted: ' . $tabTheme['accent_muted']
            . '; --tab-accent-hover: ' . $tabTheme['accent_hover'];
        ?>
        <form method="post" action="/set-media-domain.php" class="media-domain-tabs__form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="domain" value="<?= Moncine\View::escape($domainKey) ?>">
            <input type="hidden" name="redirect" value="<?= Moncine\View::escape($tabRedirect) ?>">
            <button type="submit"
                    class="media-domain-tabs__tab media-domain-tabs__tab--<?= Moncine\View::escape($domainKey) ?><?= $isActive ? ' media-domain-tabs__tab--active' : '' ?>"
                    style="<?= Moncine\View::escape($tabStyle) ?>"
                    <?= $isActive ? ' aria-current="true"' : '' ?>>
                <span class="media-domain-tabs__dot" aria-hidden="true"></span>
                <?= Moncine\View::escape($domainLabel) ?>
            </button>
        </form>
    <?php endforeach; ?>
</nav>
