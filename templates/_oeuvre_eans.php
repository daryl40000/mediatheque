<?php
/**
 * Gestion des codes EAN catalogue (admin, phase 6 bis).
 *
 * @var int $oeuvreId
 * @var list<array<string, mixed>> $oeuvreEans
 */
?>
<section class="oeuvre-eans-panel">
    <h2>Codes EAN catalogue</h2>
    <p class="hint">
        Un code par support (DVD, Blu-ray, 4K) pour faciliter les recherches d’achat futures.
    </p>

    <?php if (isset($_GET['ean_added'])): ?>
        <p class="alert alert-success">Code EAN ajouté.</p>
    <?php endif; ?>
    <?php if (isset($_GET['ean_deleted'])): ?>
        <p class="alert alert-success">Code EAN supprimé.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['ean_error'])): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['ean_error']) ?></p>
    <?php endif; ?>

    <?php if (($oeuvreEans ?? []) !== []): ?>
        <ul class="oeuvre-eans-list">
            <?php foreach ($oeuvreEans as $row): ?>
                <li>
                    <strong><?= Moncine\View::escape(
                        Moncine\SupportPhysique::label((string) ($row['support_physique'] ?? ''))
                            ?: 'Sans support'
                    ) ?></strong>
                    — <code><?= Moncine\View::escape(
                        Moncine\View::formatEan((string) ($row['ean'] ?? ''))
                    ) ?></code>
                    <?php if (trim((string) ($row['label'] ?? '')) !== ''): ?>
                        <span class="hint">(<?= Moncine\View::escape((string) $row['label']) ?>)</span>
                    <?php endif; ?>
                    <form method="post" action="/enregistrer-oeuvre-ean.php" class="inline-form">
                        <?= Moncine\View::csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="oeuvre_id" value="<?= (int) $oeuvreId ?>">
                        <input type="hidden" name="ean_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-secondary btn--small">Supprimer</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="hint">Aucun code EAN catalogue pour cette œuvre.</p>
    <?php endif; ?>

    <form method="post" action="/enregistrer-oeuvre-ean.php" class="import-form oeuvre-eans-form">
        <?= Moncine\View::csrfField() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="oeuvre_id" value="<?= (int) $oeuvreId ?>">

        <label for="oeuvre_ean_support">Support</label>
        <select name="support_physique" id="oeuvre_ean_support" required>
            <option value="">— Choisir —</option>
            <?php foreach (Moncine\SupportPhysique::choices() as $key => $label): ?>
                <option value="<?= Moncine\View::escape($key) ?>"><?= Moncine\View::escape($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="oeuvre_ean_code">Code EAN</label>
        <input type="text" name="ean" id="oeuvre_ean_code" inputmode="numeric" required
               pattern="[0-9\s-]{8,18}" maxlength="18">

        <label for="oeuvre_ean_label">Libellé (optionnel)</label>
        <input type="text" name="label" id="oeuvre_ean_label" maxlength="80"
               placeholder="Ex. Édition collector">

        <button type="submit" class="btn btn-secondary">Ajouter un EAN</button>
    </form>
</section>
