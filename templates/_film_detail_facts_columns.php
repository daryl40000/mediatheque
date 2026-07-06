<?php
/**
 * Détails film en deux colonnes (fiche bibliothèque).
 *
 * @var array<string, mixed> $film
 * @var string|null $derniereVue
 */
$film = $film ?? [];
$derniereVue = $derniereVue ?? null;
$acteurs = Moncine\FilmManualEdit::acteursList($film);
?>
<div class="game-detail-facts-grid">
    <dl class="film-facts game-detail-facts-grid__col">
        <dt>Réalisateur</dt>
        <dd><?php $name = (string) ($film['realisateur'] ?? ''); require MONCINE_ROOT . '/templates/_personne_link.php'; ?></dd>

        <?php if ($acteurs !== []): ?>
            <dt>Acteurs principaux</dt>
            <dd class="personnes-list">
                <?php foreach ($acteurs as $i => $acteurName): ?>
                    <?php if ($i > 0): ?><span class="personnes-sep">, </span><?php endif; ?>
                    <?php $name = $acteurName; require MONCINE_ROOT . '/templates/_personne_link.php'; ?>
                <?php endforeach; ?>
            </dd>
        <?php endif; ?>

        <dt>Catégorie</dt>
        <dd><?= Moncine\View::escape(Moncine\View::contentKindLabel($film)) ?></dd>

        <?php if (Moncine\MoncineContentKind::isSerie((string) ($film['moncine_kind'] ?? ''))): ?>
            <dt>Saison</dt>
            <dd><?php
                $saisonLabel = trim((string) ($film['saison_label'] ?? ''));
                $saisonNum = (int) ($film['saison_numero'] ?? 0);
                if ($saisonLabel !== '') {
                    echo Moncine\View::escape($saisonLabel);
                } elseif ($saisonNum > 0) {
                    echo 'Saison ' . $saisonNum;
                } else {
                    echo '—';
                }
                ?></dd>
        <?php endif; ?>

        <dt>Année</dt>
        <dd><?= Moncine\View::escape(Moncine\FilmRepository::formatAnnee((int) ($film['annee'] ?? 0))) ?></dd>

        <dt>Nationalité</dt>
        <dd><?= Moncine\View::escape(
            Moncine\FilmRepository::formatNationalite((string) ($film['nationalite'] ?? ''))
        ) ?></dd>
    </dl>

    <dl class="film-facts game-detail-facts-grid__col">
        <dt>Durée</dt>
        <dd><?= Moncine\View::escape(Moncine\FilmRepository::formatDuree((int) ($film['duree_min'] ?? 0))) ?></dd>

        <dt>Style</dt>
        <dd><?= ($film['styles'] ?? '') !== '' ? Moncine\View::escape($film['styles']) : '—' ?></dd>

        <dt>Format image</dt>
        <dd><?= ($film['format_image'] ?? '') !== '' ? Moncine\View::escape($film['format_image']) : '—' ?></dd>

        <dt>Bande sonore</dt>
        <dd><?= ($film['format_son'] ?? '') !== '' ? Moncine\View::escape($film['format_son']) : '—' ?></dd>

        <dt>Support physique</dt>
        <dd><?php $supportKey = (string) ($film['support_physique'] ?? ''); require MONCINE_ROOT . '/templates/_support_link.php'; ?></dd>

        <?php if (trim((string) ($film['ean'] ?? '')) !== ''): ?>
            <dt>Code-barres (EAN)</dt>
            <dd class="film-ean">
                <span class="film-ean__code"><?= Moncine\View::escape(Moncine\View::formatEan((string) $film['ean'])) ?></span>
            </dd>
        <?php endif; ?>

        <dt>Vision la plus récente</dt>
        <dd><?= !empty($derniereVue)
            ? Moncine\View::escape(Moncine\HistoriqueRepository::formatDateVue((string) $derniereVue))
            : 'Jamais' ?></dd>

        <?php if ((int) ($film['tmdb_id'] ?? 0) > 0): ?>
            <dt>Identifiant TMDB</dt>
            <dd>
                <?php
                $tmdbUrl = Moncine\TmdbMediaType::publicUrl(
                    (int) $film['tmdb_id'],
                    (string) ($film['tmdb_media_type'] ?? '')
                );
                ?>
                <a href="<?= Moncine\View::escape($tmdbUrl) ?>"
                   target="_blank" rel="noopener">
                    <?= Moncine\View::escape(Moncine\TmdbMediaType::label(
                        (string) ($film['tmdb_media_type'] ?? ''),
                        (string) ($film['tmdb_tv_kind'] ?? '')
                    )) ?>
                    #<?= (int) $film['tmdb_id'] ?>
                </a>
            </dd>
        <?php endif; ?>
    </dl>
</div>
