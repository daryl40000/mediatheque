<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\AbmApiParser;
use PHPUnit\Framework\TestCase;

final class AbmApiParserTest extends TestCase
{
    public function testParseMagazinesList(): void
    {
        $raw = 'identifiant du magazine ; Nom ; logo<br>434 ; La Puce ; logo_lapuce.jpg<br>435 ; Television ; logo_television.jpg';

        $magazines = AbmApiParser::parseMagazinesList($raw);

        $this->assertCount(2, $magazines);
        $this->assertSame(434, $magazines[0]['abm_magazine_id']);
        $this->assertSame('La Puce', $magazines[0]['titre']);
        $this->assertSame('logo_lapuce.jpg', $magazines[0]['logo_filename']);
    }

    public function testParseIssuesDumpWithHeader(): void
    {
        $raw = 'identifiant du numéro ; nom du magazine ; id magazine ; CD ; HS ; numéro ; fichier.jpg ; date ; url petite image<br>'
            . '1 ; Tilt ; 29 ;  ;  ; 033 ; tilt033petitecouverture.jpg ; mars 1986 ; http://example.org/cover1.jpg<br>'
            . '2 ; Tilt ; 29 ; CD ; HS ; HS1 ; tilths1petitecouverture.jpg ; 1987 ; http://example.org/cover2.jpg';

        $issues = AbmApiParser::parseIssuesDump($raw);

        $this->assertCount(2, $issues);
        $this->assertSame(1, $issues[0]['abm_issue_id']);
        $this->assertSame(29, $issues[0]['abm_magazine_id']);
        $this->assertFalse($issues[0]['hors_serie']);
        $this->assertSame('033', $issues[0]['numero']);
        $this->assertSame('https://example.org/cover1.jpg', $issues[0]['cover_url']);

        $this->assertTrue($issues[1]['hors_serie']);
        $this->assertTrue($issues[1]['is_cd']);
    }

    public function testBuildCatalogExportMergesSeriesAndIssues(): void
    {
        $magazines = [
            ['abm_magazine_id' => 29, 'titre' => 'Tilt', 'logo_filename' => 'logo_tilt.jpg'],
        ];
        $issues = [
            [
                'abm_issue_id' => 2,
                'abm_magazine_id' => 29,
                'magazine_titre' => 'Tilt',
                'is_cd' => false,
                'hors_serie' => false,
                'numero' => '034',
                'cover_filename' => 'b.jpg',
                'date_label' => 'avril 1986',
                'cover_url' => 'http://example.org/b.jpg',
            ],
            [
                'abm_issue_id' => 1,
                'abm_magazine_id' => 29,
                'magazine_titre' => 'Tilt',
                'is_cd' => false,
                'hors_serie' => false,
                'numero' => '033',
                'cover_filename' => 'a.jpg',
                'date_label' => 'mars 1986',
                'cover_url' => 'http://example.org/a.jpg',
            ],
        ];

        $series = AbmApiParser::buildCatalogExport($magazines, $issues);

        $this->assertCount(1, $series);
        $this->assertSame('Tilt', $series[0]['titre']);
        $this->assertCount(2, $series[0]['issues']);
        $this->assertSame('033', $series[0]['issues'][0]['numero']);
        $this->assertSame(33.0, $series[0]['issues'][0]['numero_ordre']);
        $this->assertSame(1986, $series[0]['issues'][0]['annee']);
        $this->assertSame('1986-03-01', $series[0]['issues'][0]['date_parution']);
    }

    public function testExtractYearAndNumeroOrdre(): void
    {
        $this->assertSame(1986, AbmApiParser::extractYear('mars 1986'));
        $this->assertNull(AbmApiParser::extractYear('sans année'));
        $this->assertSame(33.5, AbmApiParser::guessNumeroOrdre('033', true));
        $this->assertSame(12.0, AbmApiParser::guessNumeroOrdre('12', false));
    }

    public function testParseIssuesDumpNormalizesCoverUrlWithSpaces(): void
    {
        $raw = 'identifiant du numéro ; nom ; id ; CD ; HS ; n° ; fichier ; date ; url<br>'
            . '99 ; PC Team ; 5 ;  ;  ; CD01 ; pcteam_numerocd01.jpg ; 1996 ; '
            . 'http://www.abandonware-magazines.org/images_petitescouvertures/PC Team/pcteam_numerocd01.jpg';

        $issues = AbmApiParser::parseIssuesDump($raw);

        $this->assertCount(1, $issues);
        $this->assertSame(
            'https://www.abandonware-magazines.org/images_petitescouvertures/PC%20Team/pcteam_numerocd01.jpg',
            $issues[0]['cover_url']
        );
    }

    public function testNormalizeCoverUrlEncodesSpacesAsPercent20(): void
    {
        $this->assertSame(
            'https://www.abandonware-magazines.org/images_petitescouvertures/PC%20Team/pcteam_numerocd01.jpg',
            AbmApiParser::normalizeCoverUrl(
                'http://www.abandonware-magazines.org/images_petitescouvertures/PC Team/pcteam_numerocd01.jpg'
            )
        );
        $this->assertSame(
            'https://example.org/cover1.jpg',
            AbmApiParser::normalizeCoverUrl('http://example.org/cover1.jpg')
        );
    }
}
