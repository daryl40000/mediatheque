<?php
/**
 * Grille de tomes BD (profil public ou partage visiteur).
 *
 * @var list<array<string, mixed>> $tomes
 * @var bool $isWishlist
 * @var callable(int): string $tomeUrlForBibId
 */
$isWishlist = !empty($isWishlist);
$tomeUrlForBibId = $tomeUrlForBibId ?? static fn (int $bibId): string => Moncine\View::bdUrl($bibId);
?>
<div class="magazine-issues-grid">
    <?php foreach ($tomes as $tome):
        $bibId = (int) ($tome['id'] ?? 0);
        $tomeUrl = $bibId > 0 ? $tomeUrlForBibId($bibId) : '#';
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
