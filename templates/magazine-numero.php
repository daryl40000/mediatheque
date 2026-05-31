<?php
/** @var array<string, mixed>|null $issue */
/** @var bool $saved */
/** @var string $error */
/** @var string $dateLabel */
/** @var string $pdfUrl */
?>
<section>
    <?php if ($issue === null): ?>
        <h1>Numéro introuvable</h1>
        <p><a href="/magazines.php">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a></p>
    <?php else: ?>
        <?php
        $bibId = (int) ($issue['bib_id'] ?? 0);
        $seriesId = (int) ($issue['series_id'] ?? 0);
        $cover = Moncine\View::posterSrc(trim((string) ($issue['poster_url'] ?? '')) ?: null);
        ?>
        <p>
            <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl($seriesId)) ?>" class="btn btn-secondary btn-sm">
                ← <?= Moncine\View::escape((string) ($issue['series_titre'] ?? 'Série')) ?>
            </a>
        </p>

        <?php if ($saved || isset($_GET['added'])): ?>
            <div class="alert alert-success">Numéro enregistré.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
        <?php endif; ?>

        <div class="magazine-issue-layout">
            <div class="magazine-issue-layout__cover">
                <?php if ($cover !== ''): ?>
                    <img src="<?= $cover ?>" alt="Couverture" class="magazine-cover">
                <?php else: ?>
                    <div class="magazine-cover magazine-cover--empty" aria-hidden="true"></div>
                <?php endif; ?>
            </div>
            <div class="magazine-issue-layout__main">
                <h1><?= Moncine\View::escape((string) ($issue['series_titre'] ?? '')) ?></h1>
                <p class="lead">
                    Numéro <strong><?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?></strong>
                    · <?= Moncine\View::escape($dateLabel) ?>
                    <?php if ((int) ($issue['pages'] ?? 0) > 0): ?>
                        · <?= (int) $issue['pages'] ?> p.
                    <?php endif; ?>
                </p>

                <?php if ($pdfUrl !== ''): ?>
                    <p><a href="<?= Moncine\View::escape($pdfUrl) ?>" class="btn btn-primary" target="_blank" rel="noopener">Lire le PDF</a></p>
                <?php endif; ?>

                <section class="magazine-sommaire">
                    <h2>Sommaire</h2>
                    <?php if (trim((string) ($issue['sommaire'] ?? '')) !== ''): ?>
                        <div class="magazine-sommaire__body"><?= nl2br(Moncine\View::escape((string) $issue['sommaire'])) ?></div>
                    <?php else: ?>
                        <p class="hint">Aucun sommaire renseigné.</p>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <details class="import-columns-help">
            <summary>Modifier ce numéro</summary>
            <form method="post" action="/traiter-numero-magazine.php" enctype="multipart/form-data" class="import-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                <input type="hidden" name="series_id" value="<?= $seriesId ?>">
                <input type="hidden" name="action" value="save">

                <label for="edit_numero">Numéro</label>
                <input type="text" name="numero" id="edit_numero" required
                       value="<?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?>">

                <label for="edit_numero_ordre">Ordre de tri (numérique)</label>
                <input type="number" step="0.1" name="numero_ordre" id="edit_numero_ordre"
                       value="<?= Moncine\View::escape((string) ($issue['numero_ordre'] ?? '')) ?>">

                <label for="edit_date">Date de parution</label>
                <input type="date" name="date_parution" id="edit_date"
                       value="<?= Moncine\View::escape((string) ($issue['date_parution'] ?? '')) ?>">

                <label for="edit_pages">Nombre de pages</label>
                <input type="number" name="pages" id="edit_pages" min="0"
                       value="<?= (int) ($issue['pages'] ?? 0) ?>">

                <label for="edit_support">Support</label>
                <input type="text" name="support_physique" id="edit_support"
                       placeholder="Papier, PDF, Papier + PDF…"
                       value="<?= Moncine\View::escape((string) ($issue['support_physique'] ?? '')) ?>">

                <label class="checkbox">
                    <input type="checkbox" name="est_hors_serie" value="1"<?= !empty($issue['est_hors_serie']) ? ' checked' : '' ?>>
                    Hors-série
                </label>

                <label for="edit_sommaire">Sommaire</label>
                <textarea name="sommaire" id="edit_sommaire" rows="8"><?= Moncine\View::escape((string) ($issue['sommaire'] ?? '')) ?></textarea>

                <label for="edit_cover">Nouvelle couverture (JPEG, PNG, WebP)</label>
                <input type="file" name="cover_file" id="edit_cover" accept="image/jpeg,image/png,image/webp">

                <label for="edit_pdf">Remplacer le PDF</label>
                <input type="file" name="pdf_file" id="edit_pdf" accept="application/pdf,.pdf">

                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>

            <form method="post" action="/traiter-numero-magazine.php" class="inline-form"
                  onsubmit="return confirm('Retirer ce numéro de votre liste ?');">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                <input type="hidden" name="series_id" value="<?= $seriesId ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-secondary">Retirer de ma liste</button>
            </form>
        </details>
    <?php endif; ?>
</section>
