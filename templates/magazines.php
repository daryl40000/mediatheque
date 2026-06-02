<?php
/** @var list<array<string, mixed>> $seriesList */
/** @var string $query */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var int $totalCount */
/** @var string $moduleError */
?>
<section class="collection-page">
    <header class="collection-page__header">
    <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></h1>
        <p class="lead">
            Vos revues et magazines sont regroupés par <strong>série</strong>
            (PC Jeux, Joystick, Warhammer…). Cliquez sur une série pour voir les numéros.
        </p>
        <div class="collection-page__actions">
            <a href="/ajouter-serie-magazine.php" class="btn btn-accent">Nouvelle série</a>
        </div>
    </header>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <form method="get" action="/magazines.php" class="collection-search">
        <label for="mag_q">Rechercher une série</label>
        <input type="search" name="q" id="mag_q" value="<?= Moncine\View::escape($query) ?>" placeholder="Titre de la revue…">
        <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
    </form>

    <?php if ($totalCount === 0): ?>
        <p class="hint">Aucune série en collection. Commencez par créer une série, puis ajoutez des numéros.</p>
    <?php else: ?>
        <p class="stats"><?= (int) $totalCount ?> série(s) en collection.</p>
        <div class="magazine-series-grid">
            <?php foreach ($seriesList as $series): ?>
                <?php
                $seriesId = (int) ($series['id'] ?? 0);
                $poster = trim((string) ($series['poster_url'] ?? $series['latest_poster_url'] ?? ''));
                $posterSrc = Moncine\View::posterSrc($poster !== '' ? $poster : null);
                ?>
                <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl($seriesId)) ?>"
                   class="magazine-series-card">
                    <?php if ($posterSrc !== ''): ?>
                        <img src="<?= $posterSrc ?>" alt="" class="magazine-series-card__cover" loading="lazy">
                    <?php else: ?>
                        <div class="magazine-series-card__cover magazine-series-card__cover--empty" aria-hidden="true"></div>
                    <?php endif; ?>
                    <div class="magazine-series-card__body">
                        <h2 class="magazine-series-card__title"><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h2>
                        <p class="hint">
                            <?= Moncine\View::escape(Moncine\PublicationType::label((string) ($series['publication_type'] ?? ''))) ?>
                            · <?= (int) ($series['issue_count'] ?? 0) ?> numéro(s) possédé(s)<?= (int) ($series['issue_count'] ?? 0) === 0 ? ' — ajoutez le premier' : '' ?>
                        </p>
                        <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                            <p class="hint"><?= Moncine\View::escape((string) $series['editeur']) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
