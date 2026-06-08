<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\MagazineSubject;
use Moncine\MagazineSubjectMaintenance;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class MagazineSubjectMaintenanceTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::MAGAZINE);
        $this->loginAsAdmin();
    }

    public function testDeleteOrphanSubjectAndMergeDuplicates(): void
    {
        $this->assertTrue(MagazineSubjectMaintenance::isAvailable());

        $db = \Moncine\Database::getInstance();
        $adminId = UserContext::currentUserId();
        $maintenance = new MagazineSubjectMaintenance();
        $subjectRepo = new MagazineSubjectRepository();

        $db->prepare(
            'INSERT INTO magazine_subject (category, label, detail, parution_year) VALUES (?, ?, ?, ?)'
        )->execute([MagazineSubject::TEST, 'Typo abandonnée', 'PC', 2024]);
        $orphanId = (int) $db->lastInsertId();

        $keep = $subjectRepo->findOrCreate(MagazineSubject::TEST, 'After Life', 'PC', 2024);
        $this->assertNotNull($keep);
        $this->assertGreaterThan(0, (int) ($keep['id'] ?? 0));

        $db->prepare(
            'INSERT INTO magazine_subject (category, label, detail, parution_year) VALUES (?, ?, ?, ?)'
        )->execute([MagazineSubject::PREVIEW, 'Doublon Preview', 'PS5', 2023]);
        $duplicateId = (int) $db->lastInsertId();
        $db->prepare(
            'INSERT INTO magazine_subject (category, label, detail, parution_year) VALUES (?, ?, ?, ?)'
        )->execute([MagazineSubject::PREVIEW, 'Doublon-Preview', 'PS5', 2023]);
        $toMergeId = (int) $db->lastInsertId();

        $this->assertContains($orphanId, array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $maintenance->findOrphanSubjects(50)
        ));

        $this->assertTrue($maintenance->deleteOrphanSubject($orphanId, $adminId) === true);
        $this->assertNull($subjectRepo->findById($orphanId));

        $this->assertTrue($maintenance->mergeSubjects($duplicateId, $toMergeId, $adminId) === true);
        $this->assertNull($subjectRepo->findById($toMergeId));
        $this->assertNotNull($subjectRepo->findById($duplicateId));

        $groups = $maintenance->findDuplicateGroupsByLabelKey(50);
        $groupKeys = array_column($groups, 'key');
        $this->assertNotContains(
            MagazineSubject::PREVIEW . '|ps5|2023|' . MagazineSubject::normalizeLabelKey('Doublon Preview'),
            $groupKeys
        );
    }

    public function testPurgeOrphanSubjects(): void
    {
        $db = \Moncine\Database::getInstance();
        $maintenance = new MagazineSubjectMaintenance();

        $db->prepare(
            'INSERT INTO magazine_subject (category, label, detail, parution_year) VALUES (?, ?, ?, ?)'
        )->execute([MagazineSubject::TEST, '', 'PC', 2024]);
        $db->prepare(
            'INSERT INTO magazine_subject (category, label, detail, parution_year) VALUES (?, ?, ?, ?)'
        )->execute([MagazineSubject::DOSSIER, 'Orphelin 2', '', 2024]);

        $before = count($maintenance->findOrphanSubjects(50));
        $this->assertGreaterThanOrEqual(2, $before);

        $result = $maintenance->purgeOrphanSubjects(UserContext::currentUserId());
        $this->assertGreaterThanOrEqual(2, $result['deleted']);
        $this->assertSame([], $result['errors']);
    }
}
