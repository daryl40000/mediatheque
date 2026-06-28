<?php
/**
 * @var array{linkable_total: int, linked_count: int, unlinked_count: int} $stats
 * @var list<array<string, mixed>> $subjects
 * @var string $query
 * @var string $view
 * @var string $message
 * @var string $error
 * @var string $moduleError
 */
$view = $view ?? 'unlinked';
$query = $query ?? '';
?>
<section class="catalog-maintenance-page">
    <div class="catalog-admin-page__head">
        <div>
            <h1>Pont magazine ↔ jeux</h1>
            <p class="lead">
                Reliez rétroactivement les sujets <strong>test</strong>, <strong>preview</strong> et
                <strong>interview</strong> à une fiche jeu du catalogue.
                Le libellé libre du sujet est conservé ; le lien catalogue sert au croisement et à la recherche.
            </p>
            <p class="hint">
                <a href="/maintenance-catalogue.php">← Maintenance catalogue</a>
                · <a href="/maintenance-magazine-sujets.php">Sujets magazines (nettoyage)</a>
            </p>
        </div>
    </div>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php else: ?>
        <?php if ($message !== ''): ?>
            <p class="alert alert-success"><?= Moncine\View::escape($message) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
        <?php endif; ?>

        <section class="catalog-maintenance-stats">
            <h2 class="visually-hidden">Vue d’ensemble</h2>
            <ul class="catalog-maintenance-stats__grid">
                <li class="catalog-maintenance-stat">
                    <span class="catalog-maintenance-stat__value"><?= (int) $stats['linkable_total'] ?></span>
                    <span class="catalog-maintenance-stat__label">Sujets test / preview / interview</span>
                </li>
                <li class="catalog-maintenance-stat">
                    <span class="catalog-maintenance-stat__value"><?= (int) $stats['linked_count'] ?></span>
                    <span class="catalog-maintenance-stat__label">Reliés au catalogue jeux</span>
                </li>
                <li class="catalog-maintenance-stat<?= (int) $stats['unlinked_count'] > 0 ? ' catalog-maintenance-stat--warn' : '' ?>">
                    <span class="catalog-maintenance-stat__value"><?= (int) $stats['unlinked_count'] ?></span>
                    <span class="catalog-maintenance-stat__label">Sans lien catalogue</span>
                </li>
            </ul>
        </section>

        <section class="catalog-maintenance-panel">
            <h2><?= $view === 'linked' ? 'Sujets reliés' : 'Sujets sans lien catalogue' ?></h2>
            <p class="hint">
                <?php if ($view === 'linked'): ?>
                    Vérifiez ou retirez un lien incorrect. La recherche inclut le titre catalogue jeu.
                <?php else: ?>
                    Choisissez un jeu dans le catalogue pour chaque sujet. Des suggestions automatiques
                    sont affichées quand le libellé correspond à un titre catalogue (attention aux homonymes).
                <?php endif; ?>
            </p>

            <nav class="catalog-maintenance-tabs" aria-label="Filtre des sujets">
                <a href="/maintenance-magazine-jeux-liens.php?view=unlinked<?= $query !== '' ? '&amp;q=' . rawurlencode($query) : '' ?>"
                   class="btn btn-sm<?= $view === 'unlinked' ? ' btn-primary' : ' btn-secondary' ?>">
                    Sans lien (<?= (int) $stats['unlinked_count'] ?>)
                </a>
                <a href="/maintenance-magazine-jeux-liens.php?view=linked<?= $query !== '' ? '&amp;q=' . rawurlencode($query) : '' ?>"
                   class="btn btn-sm<?= $view === 'linked' ? ' btn-primary' : ' btn-secondary' ?>">
                    Reliés (<?= (int) $stats['linked_count'] ?>)
                </a>
            </nav>

            <form method="get" action="/maintenance-magazine-jeux-liens.php" class="catalog-maintenance-search import-form">
                <input type="hidden" name="view" value="<?= Moncine\View::escape($view) ?>">
                <label for="link_search_q">Rechercher</label>
                <input type="search" name="q" id="link_search_q" value="<?= Moncine\View::escape($query) ?>"
                       placeholder="Libellé sujet<?= $view === 'linked' ? ', titre catalogue…' : '' ?>">
                <button type="submit" class="btn btn-secondary">Filtrer</button>
                <?php if ($query !== ''): ?>
                    <a href="/maintenance-magazine-jeux-liens.php?view=<?= Moncine\View::escape($view) ?>"
                       class="btn btn-ghost btn-sm">Effacer</a>
                <?php endif; ?>
            </form>

            <?php if ($subjects === []): ?>
                <p class="alert alert-info">
                    <?= $view === 'linked'
                        ? 'Aucun sujet relié' . ($query !== '' ? ' pour cette recherche' : '') . '.'
                        : 'Aucun sujet sans lien' . ($query !== '' ? ' pour cette recherche' : '') . ' — bravo !' ?>
                </p>
            <?php else: ?>
                <table class="catalog-maintenance-table">
                    <thead>
                        <tr>
                            <th>Sujet</th>
                            <th>Numéros</th>
                            <th><?= $view === 'linked' ? 'Jeu catalogue' : 'Relier au jeu' ?></th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $subject): ?>
                            <?php
                            $subjectId = (int) ($subject['id'] ?? 0);
                            $fieldPrefix = 'link_' . $subjectId;
                            ?>
                            <tr>
                                <td>
                                    <span class="magazine-tag magazine-tag--subject">
                                        <?= Moncine\View::escape((string) ($subject['category_label'] ?? '')) ?>
                                    </span>
                                    <strong><?= Moncine\View::escape((string) ($subject['display_label'] ?? '')) ?></strong>
                                    <span class="hint">#<?= $subjectId ?></span>
                                    <?php if ($subjectId > 0): ?>
                                        · <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectUrl($subjectId)) ?>">Voir</a>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) ($subject['usage_count'] ?? 0) ?></td>
                                <td>
                                    <?php if ($view === 'linked'): ?>
                                        <?php $catalogOeuvreId = (int) ($subject['catalog_oeuvre_id'] ?? 0); ?>
                                        <strong><?= Moncine\View::escape((string) ($subject['catalog_game_label'] ?? '')) ?></strong>
                                        <?php if ($catalogOeuvreId > 0): ?>
                                            <span class="hint">
                                                · <a href="<?= Moncine\View::escape(Moncine\View::oeuvreJeuUrl($catalogOeuvreId)) ?>">Fiche catalogue</a>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php $suggestions = $subject['suggestions'] ?? []; ?>
                                        <?php if ($suggestions !== []): ?>
                                            <p class="hint">Suggestions :</p>
                                            <ul class="catalog-maintenance-suggestions" role="list">
                                                <?php foreach ($suggestions as $suggestion): ?>
                                                    <li>
                                                        <?= Moncine\View::escape((string) ($suggestion['display_label'] ?? '')) ?>
                                                        <form method="post" class="inline-form">
                                                            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                                            <input type="hidden" name="action" value="link_subject">
                                                            <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                                                            <input type="hidden" name="catalog_oeuvre_id"
                                                                   value="<?= (int) ($suggestion['oeuvre_id'] ?? 0) ?>">
                                                            <button type="submit" class="btn btn-secondary btn-sm">Relier</button>
                                                        </form>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <form method="post" class="catalog-maintenance-link-form import-form">
                                            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                            <input type="hidden" name="action" value="link_subject">
                                            <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                                            <input type="hidden" name="catalog_oeuvre_id"
                                                   id="<?= Moncine\View::escape($fieldPrefix) ?>_oeuvre_id" value="">
                                            <div class="catalog-title-autocomplete"
                                                 data-game-catalog-autocomplete
                                                 data-search-url="<?= Moncine\View::escape(Moncine\View::gameCatalogApiUrl()) ?>"
                                                 data-oeuvre-id-input="<?= Moncine\View::escape($fieldPrefix) ?>_oeuvre_id">
                                                <input type="text"
                                                       id="<?= Moncine\View::escape($fieldPrefix) ?>_search"
                                                       class="catalog-title-autocomplete__input"
                                                       autocomplete="off" autocapitalize="off" spellcheck="false"
                                                       placeholder="Rechercher un jeu catalogue…"
                                                       aria-autocomplete="list"
                                                       aria-controls="<?= Moncine\View::escape($fieldPrefix) ?>_suggestions"
                                                       aria-expanded="false">
                                                <ul class="catalog-title-autocomplete__list"
                                                    id="<?= Moncine\View::escape($fieldPrefix) ?>_suggestions"
                                                    role="listbox" hidden></ul>
                                            </div>
                                            <button type="submit" class="btn btn-secondary btn-sm">Relier</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($view === 'linked'): ?>
                                        <form method="post" class="inline-form">
                                            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                            <input type="hidden" name="action" value="link_subject">
                                            <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                                            <input type="hidden" name="catalog_oeuvre_id" value="0">
                                            <button type="submit" class="btn btn-ghost btn-sm"
                                                    onclick="return confirm('Retirer le lien catalogue de ce sujet ?');">
                                                Retirer le lien
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="catalog-maintenance-panel">
            <h2>Cas ambigus (homonymes)</h2>
            <ul class="hint">
                <li><strong>Même titre, plateformes différentes</strong> — ex. *Gran Turismo* PS1 vs PS5 : vérifiez la plateforme et l’année du sujet avant de relier.</li>
                <li><strong>Acronymes</strong> — « GTA » peut désigner plusieurs opus : préférez la fiche catalogue avec la bonne année / plateforme.</li>
                <li><strong>Lien optionnel</strong> — un sujet sans lien reste valide ; ne reliez que si vous êtes sûr.</li>
                <li><strong>Tag revue (PS5, PC…)</strong> — contexte du numéro, pas l’identité du jeu catalogue.</li>
            </ul>
            <p class="hint">Documentation complète : <code>doc/pont-magazine-jeu.md</code></p>
        </section>
    <?php endif; ?>
</section>
