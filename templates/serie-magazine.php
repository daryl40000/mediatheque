<?php
/** @var array<string, mixed>|null $series */
/** @var list<array<string, mixed>> $issues */
/** @var string $statut */
/** @var string $publicationTypeLabel */
/** @var float $suggestNumeroOrdre */
?>
<section>
    <?php if ($series === null): ?>
        <h1>Série introuvable</h1>
        <p><a href="/magazines.php">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a></p>
    <?php else: ?>
        <?php
        $seriesId = (int) ($series['id'] ?? 0);
        $posterSrc = Moncine\View::posterSrc(trim((string) ($series['poster_url'] ?? '')) ?: null);
        $isWishlist = $statut === Moncine\LibraryStatut::WISHLIST;
        ?>
        <header class="magazine-series-header">
            <p><a href="<?= $isWishlist ? '/magazines-envies.php' : '/magazines.php' ?>" class="btn btn-secondary btn-sm">← Retour</a></p>
            <div class="magazine-series-header__main">
                <?php if ($posterSrc !== ''): ?>
                    <img src="<?= $posterSrc ?>" alt="" class="magazine-cover magazine-cover--header">
                <?php endif; ?>
                <div>
                    <h1><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h1>
                    <p class="lead">
                        <?= Moncine\View::escape($publicationTypeLabel) ?>
                        <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                            · <?= Moncine\View::escape((string) $series['editeur']) ?>
                        <?php endif; ?>
                        <?php if (trim((string) ($series['issn'] ?? '')) !== ''): ?>
                            · ISSN <?= Moncine\View::escape((string) $series['issn']) ?>
                        <?php endif; ?>
                    </p>
                    <?php if (trim((string) ($series['notes'] ?? '')) !== ''): ?>
                        <p class="hint"><?= nl2br(Moncine\View::escape((string) $series['notes'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <p>
                <a href="/ajouter-numero-magazine.php?series_id=<?= $seriesId ?>&statut=<?= Moncine\View::escape($statut) ?>"
                   class="btn btn-accent">Ajouter un numéro</a>
                <a href="/modifier-serie-magazine.php?series_id=<?= $seriesId ?>"
                   class="btn btn-secondary">Modifier la série</a>
            </p>
        </header>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Numéro retiré de votre liste.</div>
        <?php endif; ?>

        <?php if ($issues === []): ?>
            <p class="hint">Aucun numéro dans cette liste. Ajoutez le premier numéro.</p>
        <?php else: ?>
            <table class="data-table magazine-issues-table">
                <thead>
                    <tr>
                        <th>Couverture</th>
                        <th>Numéro</th>
                        <th>Parution</th>
                        <th>Pages</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issues as $row): ?>
                        <?php
                        $bibId = (int) ($row['bib_id'] ?? 0);
                        $cover = Moncine\View::posterSrc(trim((string) ($row['poster_url'] ?? '')) ?: null);
                        $dateLabel = Moncine\PublicationType::formatParutionDate(
                            (string) ($row['date_parution'] ?? ''),
                            (string) ($row['publication_type'] ?? $series['publication_type'] ?? '')
                        );
                        ?>
                        <tr>
                            <td>
                                <?php if ($cover !== ''): ?>
                                    <img src="<?= $cover ?>" alt="" class="magazine-cover magazine-cover--thumb" loading="lazy">
                                <?php else: ?>
                                    <span class="magazine-cover magazine-cover--thumb magazine-cover--empty" aria-hidden="true"></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['est_hors_serie'])): ?>
                                    <span class="badge">HS</span>
                                <?php endif; ?>
                                <?= Moncine\View::escape((string) ($row['numero'] ?? '')) ?>
                            </td>
                            <td><?= Moncine\View::escape($dateLabel) ?></td>
                            <td><?= (int) ($row['pages'] ?? 0) > 0 ? (int) $row['pages'] : '—' ?></td>
                            <td><a href="<?= Moncine\View::escape(Moncine\View::magazineIssueUrl($bibId)) ?>">Ouvrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</section>
