<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\ImportFormat;
use Moncine\LibraryExportSchema;
use Moncine\CatalogExportSchema;
use Moncine\CollectionExportSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportFormatTest extends TestCase
{
    #[DataProvider('formatDetectionProvider')]
    public function testDetectFromHeader(string $expected, array $header): void
    {
        $this->assertSame($expected, ImportFormat::detectFromHeader($header));
    }

    /** @return list<array{0: string, 1: list<string>}> */
    public static function formatDetectionProvider(): array
    {
        return [
            'bibliothèque légère' => [
                ImportFormat::KIND_LIBRARY,
                LibraryExportSchema::headers(),
            ],
            'catalogue admin' => [
                ImportFormat::KIND_CATALOG,
                CatalogExportSchema::headers(),
            ],
            'export complet avec ID => catalogue' => [
                ImportFormat::KIND_CATALOG,
                CollectionExportSchema::filmHeaders(),
            ],
            'hybride oeuvre_id + statut sans métadonnées catalogue' => [
                ImportFormat::KIND_LIBRARY,
                ['ID catalogue', 'Statut', 'Support', 'Titre', 'Réalisateur'],
            ],
            'oeuvre_id + synopsis => catalogue' => [
                ImportFormat::KIND_CATALOG,
                ['ID catalogue', 'Titre', 'Réalisateur', 'Synopsis'],
            ],
            'ancien export complet sans ID => inconnu' => [
                ImportFormat::KIND_UNKNOWN,
                array_values(array_filter(
                    CollectionExportSchema::filmHeaders(),
                    static fn (string $label): bool => $label !== 'ID catalogue'
                )),
            ],
        ];
    }

    public function testLabelReturnsReadableFrench(): void
    {
        $this->assertStringContainsString('bibliothèque', ImportFormat::label(ImportFormat::KIND_LIBRARY));
        $this->assertStringContainsString('catalogue', ImportFormat::label(ImportFormat::KIND_CATALOG));
        $this->assertStringContainsString('non reconnu', ImportFormat::label(ImportFormat::KIND_UNKNOWN));
    }
}
