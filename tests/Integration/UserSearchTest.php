<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserProfile;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

final class UserSearchTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
    }

    public function testSearchByPseudoAndVilleRespectsVisibility(): void
    {
        $adminId = $this->loginAsAdmin();
        $repo = new UtilisateurRepository();

        $visibleId = $repo->create('Visible', 'visible@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($visibleId);
        $repo->updateProfile($visibleId, 'Visible', 'Jean', 'visible@test.local', 'CineFan', 'Lyon', true);

        $hiddenId = $repo->create('Hidden', 'hidden@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($hiddenId);
        $repo->updateProfile($hiddenId, 'Hidden', 'Marie', 'hidden@test.local', 'CineFan', 'Lyon', false);

        $byPseudo = $repo->searchDiscoverableUsers('CineFan', '', $adminId);
        $this->assertCount(1, $byPseudo);
        $this->assertSame($visibleId, (int) ($byPseudo[0]['id'] ?? 0));

        $byVille = $repo->searchDiscoverableUsers('', 'lyon', $adminId);
        $this->assertCount(1, $byVille);
        $this->assertSame($visibleId, (int) ($byVille[0]['id'] ?? 0));

        $both = $repo->searchDiscoverableUsers('cine', 'ly', $adminId);
        $this->assertCount(1, $both);
    }

    public function testEmptyQueryReturnsNoResults(): void
    {
        $this->loginAsAdmin();
        $repo = new UtilisateurRepository();
        $this->assertSame([], $repo->searchDiscoverableUsers('', '', 1));
    }

    public function testIsSearchableHelper(): void
    {
        $this->assertTrue(UserProfile::isSearchable(['searchable' => 1]));
        $this->assertFalse(UserProfile::isSearchable(['searchable' => 0]));
    }
}
