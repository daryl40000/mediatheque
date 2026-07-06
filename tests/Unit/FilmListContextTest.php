<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\FilmListContext;
use PHPUnit\Framework\TestCase;

final class FilmListContextTest extends TestCase
{
    public function testFilmUrlPreservesListAndSort(): void
    {
        $ctx = FilmListContext::forCollection('annee', 'desc', 'matrix', 'film');
        $url = $ctx->filmUrl(42);

        $this->assertStringContainsString('id=42', $url);
        $this->assertStringContainsString('list=collection', $url);
        $this->assertStringContainsString('sort=annee', $url);
        $this->assertStringContainsString('dir=desc', $url);
        $this->assertStringContainsString('q=matrix', $url);
        $this->assertStringContainsString('kind=film', $url);
        $this->assertStringNotContainsString('#film-list-nav', $url);
    }

    public function testWishlistBackUrl(): void
    {
        $ctx = FilmListContext::forWishlist('titre', 'asc', '');
        $this->assertSame('/souhaits.php', $ctx->backUrl());
    }
}
