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
                $suppId = (int) ($supplement['id'] ?? 0);
                $suppCover = Moncine\View::posterSrc($supplement['cover_url'] ?? null);
                $suppLabel = (string) ($supplement['display_label'] ?? 'Supplément');
                $suppUrl = $suppId > 0
                    ? Moncine\View::magazineSupplementUrl((int) $bibId, $suppId)
                    : '';
                ?>
                <figure class="livre-back-cover magazine-supplement">
                    <?php if ($suppUrl !== ''): ?>
                        <a href="<?= Moncine\View::escape($suppUrl) ?>"
                           class="magazine-supplement__link"
                           title="<?= Moncine\View::escape($suppLabel) ?>"
                           aria-label="Ouvrir le supplément : <?= Moncine\View::escape($suppLabel) ?>">
                            <?php if ($suppCover !== ''): ?>
                                <img class="livre-back-cover__img"
                                     src="<?= $suppCover ?>"
                                     alt="<?= Moncine\View::escape($suppLabel) ?>">
                            <?php else: ?>
                                <span class="livre-back-cover__img magazine-supplement__placeholder" aria-hidden="true">PDF</span>
                            <?php endif; ?>
                        </a>
                    <?php elseif ($suppCover !== ''): ?>
                        <img class="livre-back-cover__img"
                             src="<?= $suppCover ?>"
                             alt="<?= Moncine\View::escape($suppLabel) ?>">
                    <?php else: ?>
                        <span class="livre-back-cover__img magazine-supplement__placeholder" aria-hidden="true">PDF</span>
                    <?php endif; ?>
                    <figcaption class="hint livre-back-cover__caption">
                        <?php if ($suppUrl !== ''): ?>
                            <a href="<?= Moncine\View::escape($suppUrl) ?>"><?= Moncine\View::escape($suppLabel) ?></a>
                        <?php else: ?>
                            <?= Moncine\View::escape($suppLabel) ?>
                        <?php endif; ?>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</aside>
