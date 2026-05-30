<?php
/**
 * Versions recherchées sur une envie (support + EAN).
 *
 * @var int $filmId
 * @var list<array<string, mixed>> $wishlistTargets
 * @var list<array<string, mixed>> $catalogEansForOeuvre
 */
?>
<section class="wishlist-targets-panel oeuvre-eans-panel">
    <h2>Versions que je cherche</h2>
    <p class="hint">
        Indiquez le support et, si vous le connaissez, le code-barres (EAN) de l’édition souhaitée.
        Vous pouvez en renseigner plusieurs (DVD, Blu-ray, 4K…). Ces informations serviront plus tard
        pour comparer les offres sur des sites de vente.
    </p>

    <?php if (isset($_GET['wish_target_added'])): ?>
        <p class="alert alert-success">Version ajoutée à votre envie.</p>
    <?php endif; ?>
    <?php if (isset($_GET['wish_target_deleted'])): ?>
        <p class="alert alert-success">Version retirée.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['wish_target_error'])): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['wish_target_error']) ?></p>
    <?php endif; ?>

    <?php if (($wishlistTargets ?? []) !== []): ?>
        <ul class="oeuvre-eans-list wishlist-targets-list">
            <?php foreach ($wishlistTargets as $row): ?>
                <li>
                    <strong><?= Moncine\View::escape(
                        Moncine\SupportPhysique::label((string) ($row['support_physique'] ?? ''))
                    ) ?></strong>
                    <?php if (trim((string) ($row['ean'] ?? '')) !== ''): ?>
                        — <code><?= Moncine\View::escape(
                            Moncine\View::formatEan((string) ($row['ean'] ?? ''))
                        ) ?></code>
                    <?php else: ?>
                        <span class="hint">— EAN non précisé</span>
                    <?php endif; ?>
                    <?php if (trim((string) ($row['label'] ?? '')) !== ''): ?>
                        <span class="hint">(<?= Moncine\View::escape((string) $row['label']) ?>)</span>
                    <?php endif; ?>
                    <form method="post" action="/enregistrer-wishlist-cible.php" class="inline-form">
                        <?= Moncine\View::csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="film_id" value="<?= (int) $filmId ?>">
                        <input type="hidden" name="target_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-secondary btn--small">Retirer</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="hint">Aucune version précisée pour l’instant — ajoutez un support ci-dessous.</p>
    <?php endif; ?>

    <?php if (($catalogEansForOeuvre ?? []) !== []): ?>
        <h3>Depuis le catalogue</h3>
        <p class="hint">Éditions déjà référencées sur cette œuvre :</p>
        <ul class="wishlist-targets-catalog-quick">
            <?php foreach ($catalogEansForOeuvre as $catalogRow):
                $catSupport = (string) ($catalogRow['support_physique'] ?? '');
                $already = false;
                foreach ($wishlistTargets ?? [] as $existing) {
                    if ((string) ($existing['support_physique'] ?? '') === $catSupport) {
                        $already = true;
                        break;
                    }
                }
                ?>
                <li>
                    <span>
                        <?= Moncine\View::escape(Moncine\SupportPhysique::label($catSupport) ?: 'Sans support') ?>
                        — <code><?= Moncine\View::escape(
                            Moncine\View::formatEan((string) ($catalogRow['ean'] ?? ''))
                        ) ?></code>
                    </span>
                    <?php if ($already): ?>
                        <span class="hint">Déjà dans la liste</span>
                    <?php else: ?>
                        <form method="post" action="/enregistrer-wishlist-cible.php" class="inline-form">
                            <?= Moncine\View::csrfField() ?>
                            <input type="hidden" name="action" value="from_catalog">
                            <input type="hidden" name="film_id" value="<?= (int) $filmId ?>">
                            <input type="hidden" name="oeuvre_ean_id" value="<?= (int) ($catalogRow['id'] ?? 0) ?>">
                            <button type="submit" class="btn btn-secondary btn--small">Ajouter cette édition</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3>Ajouter manuellement</h3>
    <form method="post" action="/enregistrer-wishlist-cible.php" class="import-form oeuvre-eans-form">
        <?= Moncine\View::csrfField() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="film_id" value="<?= (int) $filmId ?>">

        <label for="wish_target_support">Support</label>
        <select name="support_physique" id="wish_target_support" required>
            <option value="">— Choisir —</option>
            <?php foreach (Moncine\SupportPhysique::choices() as $key => $label): ?>
                <option value="<?= Moncine\View::escape($key) ?>"><?= Moncine\View::escape($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="wish_target_ean">Code EAN (optionnel)</label>
        <input type="text" name="ean" id="wish_target_ean" inputmode="numeric" autocomplete="off"
               placeholder="13 chiffres, ex. 3760061234567" maxlength="20">

        <label for="wish_target_label">Note (optionnel)</label>
        <input type="text" name="label" id="wish_target_label" maxlength="120"
               placeholder="ex. édition digibook, VF uniquement…">

        <button type="submit" class="btn btn-primary">Ajouter cette version</button>
    </form>
</section>
