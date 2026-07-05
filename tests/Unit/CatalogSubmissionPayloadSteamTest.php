<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\CatalogSubmissionPayload;
use Moncine\MediaDomain;
use PHPUnit\Framework\TestCase;

final class CatalogSubmissionPayloadSteamTest extends TestCase
{
    public function testSteamImportMetaRoundTrip(): void
    {
        $payload = CatalogSubmissionPayload::withSteamImportMeta([
            'titre' => 'Half-Life 2',
            'submission_domain' => MediaDomain::JEU,
        ], [
            'appid' => 220,
            'playtime_forever' => 125,
            'rtime_last_played' => 1700000000,
            'img_icon_url' => 'abc123',
        ]);

        $json = CatalogSubmissionPayload::encode($payload);
        $decoded = CatalogSubmissionPayload::decode($json);

        $this->assertSame('Half-Life 2', $decoded['titre']);
        $steam = CatalogSubmissionPayload::steamImportMeta($decoded);
        $this->assertNotNull($steam);
        $this->assertSame(220, $steam['appid']);
        $this->assertSame(125, $steam['playtime_forever']);
        $this->assertSame(1700000000, $steam['rtime_last_played']);
        $this->assertSame('abc123', $steam['img_icon_url']);
    }

    public function testSteamImportMetaReturnsNullWhenMissing(): void
    {
        $decoded = CatalogSubmissionPayload::decode(
            CatalogSubmissionPayload::encode([
                'titre' => 'Jeu test',
                'submission_domain' => MediaDomain::JEU,
            ])
        );

        $this->assertNull(CatalogSubmissionPayload::steamImportMeta($decoded));
    }
}
