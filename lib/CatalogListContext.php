<?php
/**
 * Contexte de liste catalogue (tri, recherche, page) pour liens et navigation entre fiches.
 */

declare(strict_types=1);

namespace Moncine;

final class CatalogListContext
{
    public function __construct(
        private string $search = '',
        private string $sortBy = 'titre',
        private string $sortDir = 'asc',
        private int $page = 1,
        private string $mediaDomain = ''
    ) {
        $this->search = trim($this->search);
        $this->sortBy = trim($this->sortBy) !== '' ? trim($this->sortBy) : 'titre';
        $this->sortDir = strtolower(trim($this->sortDir)) === 'desc' ? 'desc' : 'asc';
        $this->page = max(1, $this->page);
        $this->mediaDomain = MediaDomain::normalizeCatalogFilter($this->mediaDomain);
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        return new self(
            (string) ($query['catalog_q'] ?? $query['q'] ?? ''),
            (string) ($query['catalog_sort'] ?? $query['sort'] ?? 'titre'),
            (string) ($query['catalog_dir'] ?? $query['dir'] ?? 'asc'),
            max(1, (int) ($query['catalog_page'] ?? $query['page'] ?? 1)),
            (string) ($query['catalog_media'] ?? $query['media'] ?? '')
        );
    }

    public function search(): string
    {
        return $this->search;
    }

    public function sortBy(): string
    {
        return $this->sortBy;
    }

    public function sortDir(): string
    {
        return $this->sortDir;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function mediaDomain(): string
    {
        return $this->mediaDomain;
    }

    public function backUrl(): string
    {
        return View::catalogueUrl($this->search, $this->sortBy, $this->sortDir, $this->page, $this->mediaDomain);
    }

    public function oeuvreUrl(int $oeuvreId): string
    {
        if ($oeuvreId <= 0) {
            return $this->backUrl();
        }

        $oeuvre = (new OeuvreRepository())->findByIdForAdmin($oeuvreId);
        if ($oeuvre === null) {
            return $this->backUrl();
        }

        $url = View::catalogOeuvreUrl(
            $oeuvre,
            $this->search,
            $this->sortBy,
            $this->sortDir,
            $this->page,
            $this->mediaDomain
        );

        return str_contains($url, '#') ? $url : $url . '#catalog-oeuvre-nav';
    }
}
