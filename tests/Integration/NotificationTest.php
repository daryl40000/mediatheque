<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\CatalogSubmission;
use Moncine\FilmManualEdit;
use Moncine\FoyerRepository;
use Moncine\NotificationRepository;
use Moncine\NotificationService;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

final class NotificationTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
    }

    public function testNewSubmissionNotifiesAdmin(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);

        Auth::logout();
        $this->startSession();

        $userId = (new UtilisateurRepository())->create(
            'Notif User',
            'notif@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($userId);
        Auth::login('notif@test.local', 'TestPass123!');

        $parsed = FilmManualEdit::parseFromPost([
            'titre' => 'Film Notif Admin',
            'realisateur' => 'Réalisateur Notif',
            'content_kind' => 'film',
        ]);
        $submitId = (new CatalogSubmission())->submit($userId, $parsed['data']);
        $this->assertIsInt($submitId);

        Auth::logout();
        $this->startSession();
        Auth::login('admin@test.local', 'TestPass123!');

        $service = new NotificationService();
        $this->assertGreaterThan(0, $service->countUnread($adminId));

        $rows = $service->listForUser($adminId);
        $this->assertNotEmpty($rows);
        $this->assertSame(NotificationRepository::KIND_SUBMISSION_NEW, $rows[0]['kind'] ?? '');
    }

    public function testApproveNotifiesSubmitter(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);

        Auth::logout();
        $this->startSession();

        $userId = (new UtilisateurRepository())->create(
            'Notif Approve',
            'approve@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($userId);
        Auth::login('approve@test.local', 'TestPass123!');

        $parsed = FilmManualEdit::parseFromPost([
            'titre' => 'Film Notif Approve',
            'realisateur' => 'Réal Approve',
            'content_kind' => 'film',
        ]);
        $submitId = (new CatalogSubmission())->submit($userId, $parsed['data']);
        $this->assertIsInt($submitId);

        Auth::logout();
        $this->startSession();
        Auth::login('admin@test.local', 'TestPass123!');

        (new CatalogSubmission())->approve($submitId, $adminId, $parsed['data'], 'Bienvenue au catalogue');

        Auth::logout();
        $this->startSession();
        Auth::login('approve@test.local', 'TestPass123!');

        $service = new NotificationService();
        $this->assertGreaterThan(0, $service->countUnread($userId));
        $rows = $service->listForUser($userId);
        $this->assertSame(NotificationRepository::KIND_SUBMISSION_APPROVED, $rows[0]['kind'] ?? '');
        $this->assertStringContainsString('acceptée', strtolower((string) ($rows[0]['title'] ?? '')));
        $this->assertNotSame('', trim((string) ($rows[0]['body'] ?? '')));
        $this->assertStringContainsString('oeuvre_id=', (string) ($rows[0]['link_url'] ?? ''));
        $this->assertGreaterThan(0, (int) ($rows[0]['related_oeuvre_id'] ?? 0));
    }

    public function testRejectNotifiesSubmitter(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);

        Auth::logout();
        $this->startSession();

        $userId = (new UtilisateurRepository())->create(
            'Notif Reject',
            'rejectnotif@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        Auth::login('rejectnotif@test.local', 'TestPass123!');

        $parsed = FilmManualEdit::parseFromPost([
            'titre' => 'Film Notif Reject',
            'realisateur' => 'Réal Reject',
            'content_kind' => 'film',
        ]);
        $submitId = (new CatalogSubmission())->submit($userId, $parsed['data']);
        $this->assertIsInt($submitId);

        Auth::logout();
        $this->startSession();
        Auth::login('admin@test.local', 'TestPass123!');

        (new CatalogSubmission())->reject($submitId, $adminId, 'Doublon');

        Auth::logout();
        $this->startSession();
        Auth::login('rejectnotif@test.local', 'TestPass123!');

        $rows = (new NotificationService())->listForUser($userId);
        $this->assertSame(NotificationRepository::KIND_SUBMISSION_REJECTED, $rows[0]['kind'] ?? '');
    }
}
