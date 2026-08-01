<?php
/**
 * Colonne gauche de la fiche magazine : couverture, suppléments, actions rapides.
 *
 * @var array<string, mixed> $issue
 * @var int $bibId
 * @var string $pdfUrl
 * @var string $popoverOpen edit|pdf
 * @var list<array<string, mixed>> $supplements
 */
$cover = Moncine\View::posterSrc(trim((string) ($issue['poster_url'] ?? '')) ?: null);
$popoverOpen = (string) ($popoverOpen ?? '');
$pdfUrl = trim((string) ($pdfUrl ?? ''));
$supplements = $supplements ?? [];
?>
<aside class="game-detail-sidebar" aria-label="Couverture et infos rapides">
    <?php if ($cover !== ''): ?>
        <img class="film-poster film-poster--large film-poster--bd game-detail-sidebar__poster" src="<?= $cover ?>"
             alt="Couverture de <?= Moncine\View::escape((string) ($issue['series_titre'] ?? 'Numéro')) ?>">
    <?php else: ?>
        <span class="film-poster film-poster--large film-poster--bd film-poster--empty game-detail-sidebar__poster" aria-hidden="true"></span>
    <?php endif; ?>

    <?php require MONCINE_ROOT . '/templates/_magazine_detail_action_popovers.php'; ?>

    <?php if ($supplements !== []): ?>
        <div class="magazine-supplements" aria-label="Suppléments et livrets bonus">
            <?php foreach ($supplements as $supplement):
                $suppCover = Moncine\View::posterSrc($supplement['cover_url'] ?? null);
                $suppLabel = (string) ($supplement['display_label'] ?? 'Supplément');
                $suppPdfUrl = trim((string) ($supplement['pdf_url'] ?? ''));
                ?>
                <figure class="livre-back-cover magazine-supplement">
                    <?php if ($suppCover !== ''): ?>
                        <button type="button"
                                class="livre-back-cover__open"
                                data-livre-cover-lightbox
                                data-cover-src="<?= $suppCover ?>"
                                data-cover-alt="<?= Moncine\View::escape($suppLabel) ?>"
                                aria-label="Agrandir : <?= Moncine\View::escape($suppLabel) ?>">
                            <img class="livre-back-cover__img"
                                 src="<?= $suppCover ?>"
                                 alt="<?= Moncine\View::escape($suppLabel) ?>">
                        </button>
                    <?php else: ?>
                        <span class="livre-back-cover__img magazine-supplement__placeholder" aria-hidden="true">PDF</span>
                    <?php endif; ?>
                    <figcaption class="hint livre-back-cover__caption">
                        <?= Moncine\View::escape($suppLabel) ?>
                        <?php if ($suppPdfUrl !== ''): ?>
                            · <a href="<?= Moncine\View::escape($suppPdfUrl) ?>" target="_blank" rel="noopener">Lire</a>
                        <?php endif; ?>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</aside>
