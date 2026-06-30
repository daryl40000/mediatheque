<?php
/**
 * Tomes d’une série BD — vue vignettes (même gabarit que les numéros magazine).
 *
 * @var list<array<string, mixed>> $tomes
 * @var bool $isWishlist
 */
?>
<div class="magazine-issues-grid" id="bd-tomes-grid">
    <?php foreach ($tomes as $tome):
        $bibId = (int) ($tome['id'] ?? 0);
        $tomeUrl = Moncine\View::bdUrl($bibId);
        $cover = Moncine\View::posterSrc(trim((string) ($tome['poster_url'] ?? '')) ?: null);
        $tomeNumero = (int) ($tome['tome_numero'] ?? 0);
        $tomeLabel = trim((string) ($tome['tome_label'] ?? ''));
        $albumTitle = trim((string) ($tome['titre'] ?? ''));
        $numeroLabel = $tomeNumero > 0 ? (string) $tomeNumero : $tomeLabel;
        $isPossessed = !empty($tome['is_possessed']);
        $cardClass = 'magazine-issue-card';
        if (!$isWishlist && !$isPossessed) {
            $cardClass .= ' magazine-issue-card--unowned';
        }
        ?>
        <article class="<?= Moncine\View::escape($cardClass) ?>">
            <a href="<?= Moncine\View::escape($tomeUrl) ?>" class="magazine-issue-card__cover-link">
                <?php if ($cover !== ''): ?>
                    <img src="<?= $cover ?>" alt="" class="magazine-cover magazine-cover--card" loading="lazy">
                <?php else: ?>
                    <span class="magazine-cover magazine-cover--card magazine-cover--empty" aria-hidden="true"></span>
                <?php endif; ?>
            </a>
            <div class="magazine-issue-card__body">
                <?php if ($numeroLabel !== ''): ?>
                    <h2 class="magazine-issue-card__title"><?= Moncine\View::escape($numeroLabel) ?></h2>
                <?php endif; ?>
                <?php if ($albumTitle !== ''): ?>
                    <p class="magazine-issue-card__meta hint"><?= Moncine\View::escape($albumTitle) ?></p>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
