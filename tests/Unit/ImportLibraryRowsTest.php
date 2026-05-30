<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\ImportFilmRows;
use Moncine\ImportLibraryRows;
use Moncine\LibraryExportSchema;
use Moncine\LibraryStatut;
use Moncine\SupportPhysique;
use PHPUnit\Framework\TestCase;

final class ImportLibraryRowsTest extends TestCase
{
    public function testRowToLibraryNormalizesStatutAndEan(): void
    {
        $header = LibraryExportSchema::headers();
        $map = ImportFilmRows::mapHeaders($header, LibraryExportSchema::COLUMN_ALIASES);

        $values = array_fill(0, count($header), '');
        $values[$map['oeuvre_id']] = '42';
        $values[$map['statut']] = 'mes envies';
        $values[$map['support_physique']] = 'blu-ray';
        $values[$map['ean']] = '978-2-1234-5678-9';
        $values[$map['vu']] = '15/01/2024';
        $values[$map['note']] = '7';

        $data = ImportLibraryRows::rowToLibrary($values, $map);

        $this->assertSame(42, $data['oeuvre_id']);
        $this->assertSame(LibraryStatut::WISHLIST, $data['statut']);
        $this->assertSame(SupportPhysique::BLURAY, $data['support_physique']);
        $this->assertSame('9782123456789', $data['ean']);
        $this->assertSame('15/01/2024', $data['_vu']);
        $this->assertSame('7', $data['_note']);
    }
}
