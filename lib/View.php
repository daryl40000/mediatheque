<?php
/**
 * Rendu simple des templates PHP (sans moteur externe).
 */

declare(strict_types=1);

namespace Moncine;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        $templateFile = MONCINE_ROOT . '/templates/' . $template . '.php';
        if (!is_file($templateFile)) {
            http_response_code(500);
            echo 'Template introuvable : ' . htmlspecialchars($template);
            return;
        }
        if (!isset($data['wideLayout']) && self::templateUsesWideLayout($template)) {
            $data['wideLayout'] = true;
        }
        $layout = $data['layout'] ?? 'default';
        if ($layout === 'print') {
            self::renderPrintLayout($templateFile, $data);

            return;
        }

        extract($data, EXTR_SKIP);
        $layoutFile = match ($layout) {
            false, 'auth' => 'layout_auth.php',
            default => 'layout.php',
        };
        require MONCINE_ROOT . '/templates/' . $layoutFile;
    }

    /**
     * Layout impression : variables de contenu isolées du layout (évite la pollution de scope).
     *
     * @param array<string, mixed> $data
     */
    private static function renderPrintLayout(string $templateFile, array $data): void
    {
        $pageTitle = (string) ($data['pageTitle'] ?? MONCINE_APP_NAME);
        $backUrl = (string) ($data['backUrl'] ?? '');
        $contentData = $data;
        unset($contentData['layout'], $contentData['pageTitle'], $contentData['backUrl'], $contentData['wideLayout']);

        require MONCINE_ROOT . '/templates/layout_print.php';
    }

    /** Pages avec tableaux larges (collection, listes…). */
    private static function templateUsesWideLayout(string $template): bool
    {
        return in_array($template, [
            'films',
            'souhaits',
            'personnes',
            'support',
            'sagas',
            'statistiques',
            'catalogue',
            'oeuvre',
            'maintenance-catalogue',
            'maintenance-medias',
            'magazines',
            'magazines-envies',
            'magazines-recherche',
            'magazine-sujet',
            'serie-magazine',
            'utilisateur-serie-magazine',
            'utilisateur-numero-magazine',
        ], true);
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Nom affiché pour un utilisateur connecté (pseudo ou prénom + nom). */
    public static function userDisplayName(array $user): string
    {
        return UserProfile::displayName($user);
    }

    /** Libellé affiché pour la catégorie d’une fiche (film, série, spectacle…). */
    public static function contentKindLabel(array $film): string
    {
        $moncineKind = MoncineContentKind::normalize((string) ($film['moncine_kind'] ?? ''));
        if ($moncineKind === MoncineContentKind::SERIE) {
            return MoncineContentKind::label(MoncineContentKind::SERIE);
        }
        if ($moncineKind === MoncineContentKind::SPECTACLE) {
            return MoncineContentKind::label(MoncineContentKind::SPECTACLE);
        }

        return TmdbMediaType::label(
            (string) ($film['tmdb_media_type'] ?? ''),
            (string) ($film['tmdb_tv_kind'] ?? '')
        );
    }

    /** Affichage d’un code EAN (chiffres seuls, comme en base). */
    public static function formatEan(string $ean): string
    {
        return OeuvreEanRepository::normalizeEan($ean);
    }

    /**
     * Résumé court des versions recherchées (liste Mes envies).
     *
     * @param list<array<string, mixed>> $targets
     */
    public static function formatWishlistTargetsSummary(array $targets): string
    {
        $parts = [];
        foreach ($targets as $row) {
            $support = SupportPhysique::label((string) ($row['support_physique'] ?? ''));
            if ($support === '') {
                continue;
            }
            $ean = OeuvreEanRepository::normalizeEan((string) ($row['ean'] ?? ''));
            $parts[] = $ean !== '' ? $support . ' · ' . self::formatEan($ean) : $support;
        }

        return implode(' ; ', $parts);
    }

    public static function userProfileUrl(int $userId, string $mediaDomain = MediaDomain::FILM): string
    {
        if ($userId <= 0) {
            return '/mes-amis.php';
        }

        $mediaDomain = MediaDomain::normalize($mediaDomain);
        $params = ['id' => (string) $userId];
        if ($mediaDomain !== MediaDomain::FILM) {
            $params['domain'] = $mediaDomain;
        }

        return '/utilisateur.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function userProfileListUrl(
        int $userId,
        string $liste,
        string $sortBy = 'titre',
        string $currentSort = 'titre',
        string $currentDir = 'asc',
        ?int $yearFilter = null,
        string $mediaDomain = MediaDomain::FILM
    ): string {
        if ($userId <= 0) {
            return '/mes-amis.php';
        }

        $mediaDomain = MediaDomain::normalize($mediaDomain);

        $liste = match ($liste) {
            'envies', 'vus' => $liste,
            default => 'collection',
        };

        $defaultDir = $liste === 'vus' ? 'desc' : 'asc';
        $dir = $defaultDir;
        if ($currentSort === $sortBy && strtolower($currentDir) === $defaultDir) {
            $dir = $defaultDir === 'asc' ? 'desc' : 'asc';
        }

        $defaultSort = $liste === 'vus' ? 'date' : 'titre';
        if ($sortBy === '' || ($liste === 'vus' && !in_array($sortBy, ['date', 'titre', 'note'], true))) {
            $sortBy = $defaultSort;
        }

        $params = [
            'id' => (string) $userId,
            'liste' => $liste,
            'sort' => $sortBy,
            'dir' => $dir,
        ];
        if ($mediaDomain !== MediaDomain::FILM) {
            $params['domain'] = $mediaDomain;
        }
        if ($yearFilter !== null && $yearFilter > 0 && $liste === 'vus') {
            $params['annee'] = (string) $yearFilter;
        }

        return '/utilisateur.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** URL d’affiche pour src : poster.php (local) ou HTTPS distant (échappée). */
    public static function posterSrc(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (PosterStorage::isLocalWebPath($url)) {
            $path = PosterStorage::filesystemPathFromWeb($url);
            if ($path !== null && is_file($path)) {
                $delivery = PosterStorage::deliveryUrlFromWeb($url);

                return $delivery !== '' ? self::escape($delivery) : '';
            }

            return '';
        }

        $safe = SecureUrl::sanitizePosterUrl($url);

        return $safe !== '' ? self::escape($safe) : '';
    }

    /** Champ caché à inclure dans les formulaires POST protégés. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="' . self::escape(Csrf::FIELD_NAME) . '" value="'
            . self::escape(Csrf::getToken()) . '">';
    }

    /** Lien vers la liste des films d’un réalisateur ou acteur. */
    /** Lien de tri pour la table « Ma collection » (clic = bascule asc/desc). */
    public static function filmsSortUrl(
        string $column,
        string $currentSort,
        string $currentDir,
        string $searchQuery = '',
        string $kindFilter = '',
        string $viewMode = ''
    ): string {
        $dir = 'asc';
        if ($currentSort === $column && strtolower($currentDir) === 'asc') {
            $dir = 'desc';
        }

        return self::filmsCollectionUrl($searchQuery, $column, $dir, $kindFilter, $viewMode);
    }

    /** Lien vers la collection (recherche, tri, filtre catégorie, mode d’affichage). */
    public static function filmsCollectionUrl(
        string $searchQuery = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $kindFilter = '',
        string $viewMode = ''
    ): string {
        $params = [];
        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if ($sortBy !== '' && $sortBy !== 'titre') {
            $params['sort'] = $sortBy;
        }
        if (strtolower($sortDir) === 'desc') {
            $params['dir'] = 'desc';
        }
        $kindFilter = ContentKindFilter::normalize($kindFilter);
        if ($kindFilter !== ContentKindFilter::ALL) {
            $params['kind'] = $kindFilter;
        }
        if (CollectionViewMode::isGrid($viewMode)) {
            $params['view'] = CollectionViewMode::GRID;
        }

        return $params === [] ? '/films.php' : '/films.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Version imprimable de la collection (mêmes filtres / tri que Mes films). */
    public static function filmsPrintUrl(
        string $searchQuery = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $kindFilter = ''
    ): string {
        $params = [];
        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if ($sortBy !== '' && $sortBy !== 'titre') {
            $params['sort'] = $sortBy;
        }
        if (strtolower($sortDir) === 'desc') {
            $params['dir'] = 'desc';
        }
        $kindFilter = ContentKindFilter::normalize($kindFilter);
        if ($kindFilter !== ContentKindFilter::ALL) {
            $params['kind'] = $kindFilter;
        }

        return $params === [] ? '/imprimer-films.php' : '/imprimer-films.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Version imprimable des envies (Mes envies ou envies du groupe). */
    public static function wishlistPrintUrl(
        string $searchQuery = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $scope = WishlistScope::MINE
    ): string {
        $params = [];
        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if (WishlistScope::normalize($scope) === WishlistScope::GROUP) {
            $params['scope'] = WishlistScope::GROUP;
        }
        if ($sortBy !== '' && $sortBy !== 'titre') {
            $params['sort'] = $sortBy;
        }
        if (strtolower($sortDir) === 'desc') {
            $params['dir'] = 'desc';
        }

        return $params === [] ? '/imprimer-envies.php' : '/imprimer-envies.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Page de choix ou formulaire d’ajout de film. */
    public static function addFilmChoiceUrl(int $oeuvreId = 0): string
    {
        if ($oeuvreId > 0) {
            return '/ajouter-film.php?oeuvre_id=' . $oeuvreId;
        }

        return '/ajouter-film.php';
    }

    /**
     * Lien depuis la recherche par personne : fiche film si déjà en bibliothèque, sinon ajout depuis le catalogue.
     *
     * @param array<string, mixed> $film
     */
    public static function personSearchFilmUrl(array $film): string
    {
        $presence = (string) ($film['library_presence'] ?? 'none');
        $bibId = (int) ($film['id'] ?? 0);
        if ($bibId > 0 && $presence !== 'none') {
            return '/film.php?id=' . $bibId;
        }

        $oeuvreId = (int) ($film['oeuvre_id'] ?? 0);

        return self::addFilmChoiceUrl($oeuvreId);
    }

    /**
     * URL ouverte au clic sur une notification (marque lue puis redirige).
     */
    public static function notificationOpenUrl(array $note): string
    {
        $id = (int) ($note['id'] ?? 0);
        if ($id > 0) {
            return '/notifications.php?read=' . $id;
        }

        return self::notificationRedirectTarget($note);
    }

    /** Destination après lecture d’une notification. */
    public static function notificationRedirectTarget(array $note): string
    {
        $kind = (string) ($note['kind'] ?? '');
        $oeuvreId = (int) ($note['related_oeuvre_id'] ?? 0);
        if ($kind === NotificationRepository::KIND_SUBMISSION_APPROVED && $oeuvreId > 0) {
            return self::addFilmChoiceUrl($oeuvreId);
        }

        $link = trim((string) ($note['link_url'] ?? ''));
        if ($link !== '' && str_starts_with($link, '/')) {
            return $link;
        }

        return '/notifications.php';
    }

    /** Texte affiché pour une notification (complète les anciennes entrées vides). */
    public static function notificationDisplayBody(array $note): string
    {
        $body = trim((string) ($note['body'] ?? ''));
        if ($body !== '') {
            return $body;
        }

        $kind = (string) ($note['kind'] ?? '');
        $oeuvreId = (int) ($note['related_oeuvre_id'] ?? 0);
        if ($kind === NotificationRepository::KIND_SUBMISSION_APPROVED) {
            $titre = '';
            if ($oeuvreId > 0) {
                $oeuvre = (new OeuvreRepository())->findById($oeuvreId);
                $titre = trim((string) ($oeuvre['titre'] ?? ''));
            }
            if ($titre !== '') {
                return '« ' . $titre . ' » est dans le catalogue. Ajoutez-le à Mes films ou à Mes envies.';
            }

            return 'Votre proposition a été acceptée. Ajoutez le film à Mes films ou à Mes envies.';
        }

        return '';
    }

    public static function addFilmUrl(string $statut, int $oeuvreId = 0): string
    {
        $statut = LibraryStatut::normalize($statut);
        $params = ['statut' => $statut];
        if ($oeuvreId > 0) {
            $params['oeuvre_id'] = (string) $oeuvreId;
        }

        return '/ajouter-film.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Fiche d’une œuvre dans le catalogue partagé. */
    public static function oeuvreUrl(
        int $oeuvreId,
        string $catalogSearch = '',
        string $catalogSort = 'titre',
        string $catalogDir = 'asc',
        int $catalogPage = 1
    ): string {
        if ($oeuvreId <= 0) {
            return self::catalogueUrl($catalogSearch, $catalogSort, $catalogDir, $catalogPage);
        }

        $params = ['id' => (string) $oeuvreId];
        $catalogSearch = trim($catalogSearch);
        if ($catalogSearch !== '') {
            $params['catalog_q'] = $catalogSearch;
        }
        if ($catalogSort !== '' && $catalogSort !== 'titre') {
            $params['catalog_sort'] = $catalogSort;
        }
        if (strtolower($catalogDir) === 'desc') {
            $params['catalog_dir'] = 'desc';
        }
        if ($catalogPage > 1) {
            $params['catalog_page'] = (string) $catalogPage;
        }

        return '/oeuvre.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) . '#catalog-oeuvre-nav';
    }

    /** Page d’administration du catalogue (liste des œuvres). */
    public static function catalogueUrl(
        string $search = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        int $page = 1
    ): string {
        $params = [];
        $search = trim($search);
        if ($search !== '') {
            $params['q'] = $search;
        }
        if ($sortBy !== '' && $sortBy !== 'titre') {
            $params['sort'] = $sortBy;
        }
        if (strtolower($sortDir) === 'desc') {
            $params['dir'] = 'desc';
        }
        if ($page > 1) {
            $params['page'] = (string) $page;
        }

        return $params === [] ? '/catalogue.php' : '/catalogue.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Lien vers la wishlist (Mes envies). */
    public static function wishlistUrl(
        string $searchQuery = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $scope = WishlistScope::MINE
    ): string {
        $params = [];
        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if (WishlistScope::normalize($scope) === WishlistScope::GROUP) {
            $params['scope'] = WishlistScope::GROUP;
        }
        if ($sortBy !== '' && $sortBy !== 'titre') {
            $params['sort'] = $sortBy;
        }
        if (strtolower($sortDir) === 'desc') {
            $params['dir'] = 'desc';
        }

        return $params === [] ? '/souhaits.php' : '/souhaits.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function wishlistSortUrl(
        string $column,
        string $currentSort,
        string $currentDir,
        string $searchQuery = '',
        string $scope = WishlistScope::MINE
    ): string {
        $dir = 'asc';
        if ($currentSort === $column && strtolower($currentDir) === 'asc') {
            $dir = 'desc';
        }

        $params = [
            'sort' => $column,
            'dir' => $dir,
        ];
        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if (WishlistScope::normalize($scope) === WishlistScope::GROUP) {
            $params['scope'] = WishlistScope::GROUP;
        }

        return '/souhaits.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** @param list<array<string, mixed>> $voters */
    public static function formatVoterNames(array $voters): string
    {
        $names = [];
        foreach ($voters as $voter) {
            $names[] = self::userDisplayName($voter);
        }

        return implode(', ', $names);
    }

    /** Indicateur visuel du tri actif (↑ ou ↓). */
    public static function filmsSortIndicator(string $column, string $currentSort, string $currentDir): string
    {
        if ($currentSort !== $column) {
            return '';
        }

        return strtolower($currentDir) === 'desc' ? ' ↓' : ' ↑';
    }

    public static function sagaUrl(string $sagaName): string
    {
        $sagaName = trim($sagaName);
        if ($sagaName === '') {
            return '/sagas.php';
        }

        return '/sagas.php?saga=' . rawurlencode($sagaName);
    }

    public static function supportFilterUrl(string $supportKey): string
    {
        if (!SupportPhysique::isValid($supportKey)) {
            return '/support.php';
        }

        return '/support.php?type=' . rawurlencode($supportKey);
    }

    public static function personSearchUrl(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '/personnes.php';
        }

        return '/personnes.php?q=' . rawurlencode($name);
    }

    public static function magazinesUrl(string $query = '', string $sort = 'titre', string $dir = 'asc'): string
    {
        $params = array_filter([
            'q' => trim($query),
            'sort' => $sort,
            'dir' => $dir,
        ], static fn (string $v): bool => $v !== '');

        return $params === [] ? '/magazines.php' : '/magazines.php?' . http_build_query($params);
    }

    public static function magazineSeriesUrl(
        int $seriesId,
        string $sort = 'numero_ordre',
        string $dir = 'desc',
        array $queryExtra = []
    ): string {
        if ($seriesId <= 0) {
            return '/magazines.php';
        }

        $params = [
            'series_id' => $seriesId,
            'sort' => $sort,
            'dir' => $dir,
        ];

        foreach ($queryExtra as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $value = is_string($value) ? trim($value) : (string) $value;
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return '/serie-magazine.php?' . http_build_query($params);
    }

    /** Liste imprimable / PDF d’une série (mêmes filtres que la page série). */
    public static function magazineSeriesPrintUrl(
        int $seriesId,
        string $sort = 'numero_ordre',
        string $dir = 'desc',
        array $queryExtra = []
    ): string {
        if ($seriesId <= 0) {
            return '/magazines.php';
        }

        $params = [
            'series_id' => $seriesId,
            'sort' => $sort,
            'dir' => $dir,
        ];

        foreach ($queryExtra as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $value = is_string($value) ? trim($value) : (string) $value;
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return '/imprimer-serie-magazine.php?' . http_build_query($params);
    }

    public static function magazineIssueUrl(int $bibId): string
    {
        return $bibId > 0 ? '/magazine-numero.php?id=' . $bibId : '/magazines.php';
    }

    public static function magazineSubjectSearchUrl(): string
    {
        return '/magazines-recherche.php';
    }

    public static function magazineSubjectUrl(int $subjectId): string
    {
        return $subjectId > 0
            ? '/magazine-sujet.php?id=' . $subjectId
            : self::magazineSubjectSearchUrl();
    }

    public static function magazineSubjectApiUrl(): string
    {
        return '/rechercher-sujets-magazine.php';
    }

    public static function userProfileMagazineSeriesUrl(
        int $targetUserId,
        int $seriesId,
        string $listMode = 'collection',
        string $sort = 'numero_ordre',
        string $dir = 'desc',
        array $queryExtra = []
    ): string {
        if ($targetUserId <= 0 || $seriesId <= 0) {
            return self::userProfileUrl($targetUserId, MediaDomain::MAGAZINE);
        }

        $statut = $listMode === 'envies' ? LibraryStatut::WISHLIST : LibraryStatut::COLLECTION;
        $params = [
            'id' => (string) $targetUserId,
            'series_id' => (string) $seriesId,
            'statut' => $statut,
            'sort' => $sort,
            'dir' => $dir,
        ];

        foreach ($queryExtra as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $value = is_string($value) ? trim($value) : (string) $value;
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return '/utilisateur-serie-magazine.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function userProfileMagazineIssueUrl(int $targetUserId, int $bibId): string
    {
        if ($targetUserId <= 0 || $bibId <= 0) {
            return self::userProfileUrl($targetUserId, MediaDomain::MAGAZINE);
        }

        return '/utilisateur-numero-magazine.php?' . http_build_query([
            'id' => (string) $targetUserId,
            'bib_id' => (string) $bibId,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
