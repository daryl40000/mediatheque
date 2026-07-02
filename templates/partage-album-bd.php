<?php
/**
 * @var array<string, mixed>|null $tome
 */
?>
<section class="share-visitor-page">
    <?php if ($tome === null): ?>
        <h1>Tome introuvable</h1>
        <p class="hint">Ce tome n’est pas accessible via ce lien de partage.</p>
        <p><a href="<?= Moncine\View::escape((string) ($listUrl ?? '/partage-bd.php')) ?>" class="btn btn-secondary">← Retour à la liste</a></p>
    <?php else: ?>
        <?php
        $cover = Moncine\View::posterSrc(trim((string) ($tome['poster_url'] ?? '')) ?: null);
        $tomeNumero = (int) ($tome['tome_numero'] ?? 0);
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape((string) ($listUrl ?? '/partage-bd.php')) ?>">Liste partagée</a>
            <span aria-hidden="true"> › </span>
            <a href="<?= Moncine\View::escape((string) ($seriesUrl ?? $listUrl ?? '/partage-bd.php')) ?>">
                <?= Moncine\View::escape((string) ($tome['series_titre'] ?? 'Série')) ?>
            </a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape(Moncine\BdRowMapper::tomeSummary($tome) ?: 'Tome') ?></span>
        </p>

        <div class="magazine-issue-layout">
            <div class="magazine-issue-layout__cover">
                <?php if ($cover !== ''): ?>
                    <img src="<?= $cover ?>" alt="Couverture" class="magazine-cover film-poster--large film-poster--bd">
                <?php else: ?>
                    <div class="magazine-cover magazine-cover--empty" aria-hidden="true"></div>
                <?php endif; ?>
            </div>
            <div class="magazine-issue-layout__main">
                <h1><?= Moncine\View::escape((string) ($tome['display_titre'] ?? $tome['titre'] ?? '')) ?></h1>
                <p class="lead">
                    <?php if (trim((string) ($tome['tome_label'] ?? '')) === ''): ?>
                        <?php if (!empty($tome['est_hors_serie'])): ?>
                            <span class="badge">HS</span>
                        <?php endif; ?>
                        Tome <strong><?= $tomeNumero ?></strong>
                    <?php else: ?>
                        <?= Moncine\View::escape((string) $tome['tome_label']) ?>
                    <?php endif; ?>
                    <?php if ((int) ($tome['annee'] ?? 0) > 0): ?>
                        · <?= (int) $tome['annee'] ?>
                    <?php endif; ?>
                    <?php if (($supportLabel ?? '') !== ''): ?>
                        · <?= Moncine\View::escape($supportLabel) ?>
                    <?php endif; ?>
                    <?php if (!Moncine\BdPossession::isPossessed($tome)): ?>
                        <span class="magazine-tag magazine-tag--none"><?= Moncine\View::escape((string) ($possessionLabel ?? 'Non possédé')) ?></span>
                    <?php endif; ?>
                </p>
                <p class="hint">Fiche en lecture seule — <?= Moncine\View::escape((string) ($scopeLabel ?? '')) ?>.</p>
            </div>
        </div>

        <p>
            <a href="<?= Moncine\View::escape((string) ($seriesUrl ?? $listUrl ?? '/partage-bd.php')) ?>" class="btn btn-secondary">← Retour à la série</a>
        </p>
    <?php endif; ?>
</section>
