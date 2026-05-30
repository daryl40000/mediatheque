<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\RequestClientIp;
use Moncine\SchemaMigrator;
use Moncine\ShareLinkRateLimit;
use Moncine\ShareLinkScope;
use Moncine\ShareLinkService;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class ShareSecurityTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        ShareLinkRateLimit::resetForTests();
    }

    public function testShareLinkQuotaBlocksEleventhActiveLink(): void
    {
        $userId = $this->loginAsAdmin();
        $foyerId = UserContext::currentFoyerId();
        $service = new ShareLinkService();
        $max = ShareLinkService::MAX_ACTIVE_LINKS_PER_USER;

        for ($i = 0; $i < $max; $i++) {
            $result = $service->create($userId, $foyerId, ShareLinkScope::COLLECTION, 'lien' . $i);
            $this->assertIsArray($result, 'Link ' . $i);
        }

        $blocked = $service->create($userId, $foyerId, ShareLinkScope::WISHLIST, 'trop');
        $this->assertIsString($blocked);
        $this->assertStringContainsString('Limite', $blocked);
    }

    public function testShareLinkRateLimitBlocksAfterManyFailuresFromSameIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.99';
        ShareLinkRateLimit::resetForTests();

        $this->assertTrue(ShareLinkRateLimit::allowAttempt());

        for ($i = 0; $i < 40; $i++) {
            ShareLinkRateLimit::recordFailure();
        }

        $this->assertFalse(ShareLinkRateLimit::allowAttempt());

        $service = new ShareLinkService();
        $this->assertNull($service->resolve('jeton-invalide-pour-test-rate-limit'));
    }

    public function testRequestClientIpUsesRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.42';
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);

        $this->assertSame('198.51.100.42', RequestClientIp::resolve());
    }
}
