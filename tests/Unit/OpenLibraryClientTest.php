<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\OpenLibraryClient;
use PHPUnit\Framework\TestCase;

final class OpenLibraryClientTest extends TestCase
{
    public function testNormalizeIsbnStripsDashesAndAcceptsIsbn13(): void
    {
        $this->assertSame('9782070360024', OpenLibraryClient::normalizeIsbn('978-2-07-036002-4'));
        $this->assertSame('', OpenLibraryClient::normalizeIsbn('abc'));
        $this->assertSame('0451526538', OpenLibraryClient::normalizeIsbn('0-451-52653-8'));
    }

    public function testNormalizeEditionIdFromUrlAndKey(): void
    {
        $this->assertSame('OL58676659M', OpenLibraryClient::normalizeEditionId('OL58676659M'));
        $this->assertSame(
            'OL58676659M',
            OpenLibraryClient::normalizeEditionId('https://openlibrary.org/books/OL58676659M/Letranger')
        );
        $this->assertSame('OL58676659M', OpenLibraryClient::normalizeEditionId('/books/OL58676659M'));
        $this->assertSame('', OpenLibraryClient::normalizeEditionId('OL123W'));
    }

    public function testCoverAndPublicUrls(): void
    {
        $this->assertStringContainsString(
            '/b/isbn/9782070360024-L.jpg',
            OpenLibraryClient::coverUrlForIsbn('978-2-07-036002-4')
        );
        $this->assertSame(
            'https://openlibrary.org/books/OL58676659M',
            OpenLibraryClient::publicEditionUrl('OL58676659M')
        );
        $this->assertSame(1942, OpenLibraryClient::extractYear('07-01-1942'));
        $this->assertSame(0, OpenLibraryClient::extractYear('sans date'));
    }

    public function testParseSeriesHintSplitsNameAndNumber(): void
    {
        $this->assertSame(
            ['saga' => 'Harry Potter', 'saga_ordre' => 1],
            OpenLibraryClient::parseSeriesHint('Harry Potter, #1')
        );
        $this->assertSame(
            ['saga' => 'Discworld', 'saga_ordre' => 8],
            OpenLibraryClient::parseSeriesHint('Discworld #8')
        );
        $this->assertSame(
            ['saga' => 'Fondation', 'saga_ordre' => 2],
            OpenLibraryClient::parseSeriesHint('Fondation, tome 2')
        );
        $this->assertSame(
            ['saga' => 'Seule la saga', 'saga_ordre' => 0],
            OpenLibraryClient::parseSeriesHint('Seule la saga')
        );
    }

    public function testParseSeriesPositionAndSearchDoc(): void
    {
        $this->assertSame(1, OpenLibraryClient::parseSeriesPosition('1'));
        $this->assertSame(1, OpenLibraryClient::parseSeriesPosition('1-3'));
        $this->assertSame(8, OpenLibraryClient::parseSeriesPosition('Book 8'));
        $this->assertSame(0, OpenLibraryClient::parseSeriesPosition(''));

        $parsed = OpenLibraryClient::parseSeriesFromSearchDoc([
            'series_name' => ['Harry Potter'],
            'series_position' => ['1'],
        ]);
        $this->assertSame('Harry Potter', $parsed['saga']);
        $this->assertSame(1, $parsed['saga_ordre']);

        $fromEdition = OpenLibraryClient::parseSeriesFromEditionField(['Harry Potter, #1']);
        $this->assertSame('Harry Potter', $fromEdition['saga']);
        $this->assertSame(1, $fromEdition['saga_ordre']);
    }
}
