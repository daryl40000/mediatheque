<?php
/**
 * URLs des fiches catalogue (œuvres partagées) avec paramètres de retour liste.
 */

declare(strict_types=1);

namespace Moncine;

final class CatalogPageUrls
{
    public static function catalogOeuvrePageUrl(
        string $path,
        int $oeuvreId,
        string $catalogSearch = '',
        string $catalogSort = 'titre',
        string $catalogDir = 'asc',
        int $catalogPage = 1,
        string $catalogMedia = ''
    ): string {
        if ($oeuvreId <= 0) {
            return View::catalogueUrl($catalogSearch, $catalogSort, $catalogDir, $catalogPage, $catalogMedia);
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
        $catalogMedia = MediaDomain::normalizeCatalogFilter($catalogMedia);
        if ($catalogMedia !== '') {
            $params['catalog_media'] = $catalogMedia;
        }

        return $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) . '#catalog-oeuvre-nav';
    }
}
