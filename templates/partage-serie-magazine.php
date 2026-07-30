<?php
/**
 * @var array<string, mixed>|null $link
 * @var array<string, mixed>|null $series
 * @var list<array<string, mixed>> $issues
 */
?>
<section class="collection-page share-visitor-page">
    <?php if ($link === null || $series === null): ?>
        <h1>Série introuvable</h1>
        <p class="hint">Ce lien ne permet pas d’afficher cette série.</p>
        <p><a href="<?= Moncine\View::escape((string) ($listUrl ?? '/partage-magazines.php')) ?>" class="btn btn-secondary">← Retour à la liste</a></p>
    <?php else: ?>
        <?php
        $posterSrc = Moncine\View::seriesPosterSrc($series);
        ?>
        <p>
            <a href="<?= Moncine\View::escape((string) ($listUrl ?? '/partage-magazines.php')) ?>" class="btn btn-secondary btn-sm">← Retour à la liste</a>
        </p>

        <header class="magazine-series-header">
            <div class="magazine-series-header__main">
                <?php if ($posterSrc !== ''): ?>
                    <img src="<?= $posterSrc ?>" alt="" class="magazine-cover magazine-cover--header">
                <?php endif; ?>
                <div>
                    <h1><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h1>
                    <p class="lead">
                        <?= Moncine\View::escape((string) ($publicationTypeLabel ?? '')) ?>
                        · Partagé par <strong><?= Moncine\View::escape((string) ($ownerLabel ?? '')) ?></strong>
                    </p>
                    <p class="hint">Lecture seule.</p>
                </div>
            </div>
        </header>

        <?php if ($issues === []): ?>
            <p class="hint">Aucun numéro dans cette série.</p>
        <?php else: ?>
            <p class="stats"><?= (int) ($totalCount ?? count($issues)) ?> numéro<?= (int) ($totalCount ?? count($issues)) > 1 ? 's' : '' ?>.</p>
            <ul class="magazine-series-list">
                <?php foreach ($issues as $issue): ?>
                    <li>
                        <strong>
                            <?php if (!empty($issue['est_hors_serie'])): ?>
                                <span class="magazine-tag">HS</span>
                            <?php endif; ?>
                            N° <?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?>
                        </strong>
                        <?php
                        $dateLabel = Moncine\PublicationType::formatParutionDate(
                            (string) ($issue['date_parution'] ?? ''),
                            (string) ($issue['publication_type'] ?? $series['publication_type'] ?? '')
                        );
                        if ($dateLabel !== ''):
                            ?>
                            <span class="hint"> — <?= Moncine\View::escape($dateLabel) ?></span>
                        <?php endif; ?>
                        <span class="hint"> — <?= Moncine\View::escape(Moncine\MagazineSupport::possessionStatusLabel($issue)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
