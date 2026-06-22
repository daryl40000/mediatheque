<?php
/** @var string $templateFile Fichier de contenu injecté par View::render */
use Moncine\Auth;
use Moncine\NotificationService;

$isAdminCatalog = Moncine\CatalogAdmin::canAccess();
$submissionsAvailable = Moncine\CatalogSubmission::isAvailable();
$canProposeToCatalog = $submissionsAvailable && !$isAdminCatalog;
$pendingSubmissions = $isAdminCatalog && $submissionsAvailable
    ? (new Moncine\CatalogSubmission())->countPending()
    : 0;
$pendingRegistrations = $isAdminCatalog && Moncine\RegistrationService::isAvailable()
    ? (new Moncine\RegistrationService())->countPendingAdmin()
    : 0;
$currentUserId = Auth::currentUserId();
$notificationsAvailable = NotificationService::isAvailable() && $currentUserId > 0;
$unreadNotifications = $notificationsAvailable
    ? (new NotificationService())->countUnread($currentUserId)
    : 0;
$currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$mediaDomain = isset($pageMediaDomain)
    ? Moncine\MediaDomain::normalize((string) $pageMediaDomain)
    : Moncine\MediaContext::current();
$profileUrl = $currentUserId > 0 ? Moncine\View::userProfileUrl($currentUserId, $mediaDomain) : '';
$mediaNav = Moncine\MediaContext::navLabels();
$mediaTheme = Moncine\MediaDomain::theme($mediaDomain);
$collectionPath = Moncine\MediaDomain::collectionPath($mediaDomain);
$wishlistPath = Moncine\MediaDomain::wishlistPath($mediaDomain);
$showFilmOnlyNav = Moncine\MediaDomain::hasFilmOnlyFeatures($mediaDomain);
$mediaCssVars = implode('; ', [
    '--media-accent: ' . $mediaTheme['accent'],
    '--media-accent-hover: ' . $mediaTheme['accent_hover'],
    '--media-accent-muted: ' . $mediaTheme['accent_muted'],
    '--media-bar-bg: ' . $mediaTheme['bar_bg'],
    '--media-header-tint: ' . $mediaTheme['header_tint'],
    '--media-body-tint: ' . $mediaTheme['body_tint'],
    '--accent: ' . $mediaTheme['accent'],
    '--accent-hover: ' . $mediaTheme['accent_hover'],
]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= Moncine\View::escape($pageTitle ?? MONCINE_APP_NAME) ?> — <?= Moncine\View::escape(MONCINE_APP_NAME) ?></title>
    <link rel="icon" href="/assets/img/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body class="media-domain--<?= Moncine\View::escape($mediaDomain) ?><?= !empty($wideLayout) ? ' page-wide' : '' ?>"
      data-media-domain="<?= Moncine\View::escape($mediaDomain) ?>"
      style="<?= Moncine\View::escape($mediaCssVars) ?>">
    <?php require MONCINE_ROOT . '/templates/_media_domain_tabs.php'; ?>
    <header class="site-header" id="site-header">
        <div class="container site-header__inner">
            <div class="site-header__brand">
                <?php
                $logoClass = '';
                require MONCINE_ROOT . '/templates/_site_logo.php';
                ?>
                <?php if ($profileUrl !== ''): ?>
                    <?php
                    $profileLabel = 'Mon profil';
                    $profileCurrent = $currentPath === '/utilisateur.php'
                        && (int) ($_GET['id'] ?? 0) === $currentUserId;
                    ?>
                    <a href="<?= Moncine\View::escape($profileUrl) ?>"
                       class="header-profile<?= $profileCurrent ? ' header-profile--current' : '' ?>"
                       aria-label="<?= Moncine\View::escape($profileLabel) ?>"
                       title="<?= Moncine\View::escape($profileLabel) ?>">
                        <svg class="header-profile__icon" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 2.25c-4.28 0-7.75 2.47-7.75 5.5V21h15.5v-1.25c0-3.03-3.47-5.5-7.75-5.5Z"/>
                        </svg>
                    </a>
                <?php endif; ?>
                <?php if ($notificationsAvailable): ?>
                    <?php
                    $notifLabel = 'Notifications';
                    if ($unreadNotifications > 0) {
                        $notifLabel .= ' — ' . (int) $unreadNotifications . ' non lue' . ($unreadNotifications > 1 ? 's' : '');
                    }
                    ?>
                    <a href="/notifications.php"
                       class="header-notifications<?= $currentPath === '/notifications.php' ? ' header-notifications--current' : '' ?>"
                       aria-label="<?= Moncine\View::escape($notifLabel) ?>"
                       title="<?= Moncine\View::escape($notifLabel) ?>">
                        <svg class="header-notifications__icon" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.25-6.77V3a.75.75 0 0 0-1.5 0v1.26A7.002 7.002 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z"/>
                        </svg>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="header-notifications__badge" aria-hidden="true"><?= $unreadNotifications > 9 ? '9+' : (int) $unreadNotifications ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            </div>
            <button type="button" class="nav-toggle" id="nav-toggle"
                    aria-expanded="false" aria-controls="site-nav"
                    aria-label="Ouvrir le menu">
                <span class="nav-toggle__bar" aria-hidden="true"></span>
                <span class="nav-toggle__bar" aria-hidden="true"></span>
                <span class="nav-toggle__bar" aria-hidden="true"></span>
            </button>
            <nav class="site-nav" id="site-nav" aria-label="Navigation principale">
                <a href="/"<?= $currentPath === '/' ? ' aria-current="page"' : '' ?>>Accueil</a>
                <?php if ($showFilmOnlyNav): ?>
                    <a href="/quiz.php"<?= $currentPath === '/quiz.php' ? ' aria-current="page"' : '' ?>>Ce soir</a>
                <?php endif; ?>
                <a href="<?= Moncine\View::escape($collectionPath) ?>"<?= $currentPath === $collectionPath ? ' aria-current="page"' : '' ?>><?= Moncine\View::escape($mediaNav['collection']) ?></a>
                <a href="<?= Moncine\View::escape($wishlistPath) ?>"<?= $currentPath === $wishlistPath ? ' aria-current="page"' : '' ?>><?= Moncine\View::escape($mediaNav['wishlist']) ?></a>
                <a href="/statistiques.php"<?= $currentPath === '/statistiques.php' ? ' aria-current="page"' : '' ?>><?= Moncine\View::escape($mediaNav['stats']) ?></a>

                <?php
                $parametresPaths = [
                    '/parametres.php',
                    '/mon-compte.php',
                    '/mes-amis.php',
                    '/mes-groupes.php',
                    '/mes-prets.php',
                    '/utilisateur.php',
                    '/rechercher-utilisateurs.php',
                    '/import.php',
                    '/proposer-oeuvre.php',
                    '/mes-soumissions.php',
                ];
                $parametresOpen = in_array($currentPath, $parametresPaths, true);
                ?>
                <details class="site-nav__menu site-nav__menu--parametres"<?= $parametresOpen ? ' open' : '' ?>>
                    <summary class="site-nav__menu-summary site-nav__settings">Paramètres</summary>
                    <div class="site-nav__submenu" role="group" aria-label="Paramètres et compte">
                        <a href="/parametres.php"<?= in_array($currentPath, ['/parametres.php', '/mon-compte.php'], true) ? ' aria-current="page"' : '' ?>>Compte</a>
                        <a href="/mes-amis.php"<?= $currentPath === '/mes-amis.php' ? ' aria-current="page"' : '' ?>>Mes amis</a>
                        <a href="/mes-groupes.php"<?= $currentPath === '/mes-groupes.php' ? ' aria-current="page"' : '' ?>>Mon groupe famille</a>
                        <a href="/mes-prets.php"<?= $currentPath === '/mes-prets.php' ? ' aria-current="page"' : '' ?>>Mes prêts</a>
                        <a href="/rechercher-utilisateurs.php"<?= $currentPath === '/rechercher-utilisateurs.php' ? ' aria-current="page"' : '' ?>>Rechercher des utilisateurs</a>
                        <?php if ($canProposeToCatalog): ?>
                            <a href="/proposer-oeuvre.php"<?= in_array($currentPath, ['/proposer-oeuvre.php', '/mes-soumissions.php'], true) ? ' aria-current="page"' : '' ?>>
                                Proposer au catalogue
                            </a>
                        <?php endif; ?>
                        <a href="/import.php"<?= $currentPath === '/import.php' ? ' aria-current="page"' : '' ?>>Importer</a>
                    </div>
                </details>

                <?php if ($isAdminCatalog): ?>
                    <?php
                    $gestionPaths = [
                        '/catalogue.php',
                        '/soumissions-catalogue.php',
                        '/demandes-inscription.php',
                        '/maintenance-catalogue.php',
                        '/import-catalogue-magazines.php',
                        '/maintenance-medias.php',
                        '/maintenance-magazine-sujets.php',
                        '/foyers.php',
                        '/utilisateurs.php',
                    ];
                    $gestionOpen = in_array($currentPath, $gestionPaths, true);
                    ?>
                    <details class="site-nav__menu site-nav__menu--gestion"<?= $gestionOpen ? ' open' : '' ?>>
                        <summary class="site-nav__menu-summary site-nav__admin">Gestion</summary>
                        <div class="site-nav__submenu" role="group" aria-label="Gestion administrateur">
                            <a href="/catalogue.php" class="site-nav__admin"<?= $currentPath === '/catalogue.php' ? ' aria-current="page"' : '' ?>>Catalogue</a>
                            <?php if ($submissionsAvailable): ?>
                                <a href="/soumissions-catalogue.php" class="site-nav__admin"<?= $currentPath === '/soumissions-catalogue.php' ? ' aria-current="page"' : '' ?>>
                                    Soumissions<?= $pendingSubmissions > 0 ? ' (' . (int) $pendingSubmissions . ')' : '' ?>
                                </a>
                            <?php endif; ?>
                            <?php if (Moncine\RegistrationService::isAvailable()): ?>
                                <a href="/demandes-inscription.php" class="site-nav__admin"<?= $currentPath === '/demandes-inscription.php' ? ' aria-current="page"' : '' ?>>
                                    Inscriptions<?= $pendingRegistrations > 0 ? ' (' . (int) $pendingRegistrations . ')' : '' ?>
                                </a>
                            <?php endif; ?>
                            <a href="/maintenance-catalogue.php" class="site-nav__admin"<?= $currentPath === '/maintenance-catalogue.php' ? ' aria-current="page"' : '' ?>>Maintenance</a>
                            <a href="/import-catalogue-magazines.php" class="site-nav__admin"<?= $currentPath === '/import-catalogue-magazines.php' ? ' aria-current="page"' : '' ?>>Import magazines</a>
                            <a href="/maintenance-magazine-sujets.php" class="site-nav__admin"<?= $currentPath === '/maintenance-magazine-sujets.php' ? ' aria-current="page"' : '' ?>>Sujets magazines</a>
                            <a href="/maintenance-medias.php" class="site-nav__admin"<?= $currentPath === '/maintenance-medias.php' ? ' aria-current="page"' : '' ?>>Médias</a>
                            <a href="/foyers.php" class="site-nav__admin"<?= $currentPath === '/foyers.php' ? ' aria-current="page"' : '' ?>>Groupes famille</a>
                            <a href="/utilisateurs.php" class="site-nav__admin"<?= $currentPath === '/utilisateurs.php' ? ' aria-current="page"' : '' ?>>Comptes utilisateurs</a>
                        </div>
                    </details>
                <?php endif; ?>

                <?php /* POST + jeton CSRF : évite une déconnexion forcée par un simple lien */ ?>
                <form method="post" action="/deconnexion.php" class="inline-form site-nav__logout-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <button type="submit" class="site-nav__logout btn-link">Déconnexion</button>
                </form>
            </nav>
        </div>
    </header>
    <main class="container<?= !empty($wideLayout) ? ' container--wide' : '' ?>">
        <?php if (!empty($_GET['csrf_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape(Moncine\Csrf::REJECT_MESSAGE) ?>
                Si vous envoyiez un gros PDF, vérifiez les limites PHP (upload <?= Moncine\View::escape(Moncine\UploadLimits::uploadMaxFilesizeLabel()) ?>,
                post <?= Moncine\View::escape(Moncine\UploadLimits::postMaxSizeLabel()) ?>).</p>
        <?php endif; ?>
        <?php require $templateFile; ?>
    </main>
    <footer class="site-footer container">
        <p><?= Moncine\View::escape($mediaNav['footer']) ?> — <?= Moncine\View::escape(MONCINE_APP_NAME) ?></p>
    </footer>
    <script src="/assets/js/app.js" defer></script>
</body>
</html>
