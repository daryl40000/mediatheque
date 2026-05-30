<?php
/** @var list<string> $styleChoices tous les genres disponibles */
/** @var list<string> $selectedStyles genres cochés lors de la dernière session */
/** @var list<string> $nationaliteChoices pays disponibles */
/** @var list<string> $selectedNationalites pays cochés lors de la dernière session */
/** @var array<string, mixed> $saved */
?>
<section>
    <h1>Questionnaire du soir</h1>
    <p class="lead">Répondez à quelques questions pour affiner la proposition.</p>

    <form method="post" action="/resultat.php" class="quiz-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <fieldset>
            <legend>1. Durée du film</legend>
            <?php
            $dureeChoices = Moncine\QuizSession::dureeFilmChoiceLabels();
            $currentDuree = (string) ($saved['duree_film'] ?? 'moyen');
            if (!isset($dureeChoices[$currentDuree])) {
                $currentDuree = 'moyen';
            }
            foreach ($dureeChoices as $value => $label):
                $chk = $currentDuree === $value ? ' checked' : '';
                ?>
                <label class="radio">
                    <input type="radio" name="duree_film" value="<?= Moncine\View::escape($value) ?>"<?= $chk ?>>
                    <?= Moncine\View::escape($label) ?>
                </label>
            <?php endforeach; ?>
            <p class="hint">
                Avec « Peu importe », toutes les durées sont possibles.
                Sinon, seuls les films dont la durée est connue sont filtrés (les autres restent possibles).
            </p>
        </fieldset>

        <fieldset>
            <legend>2. Type</legend>
            <p class="hint">Film, série TV, documentaire ou spectacle (concert, one-man show…).</p>
            <?php
            $kindChoices = Moncine\ContentKindFilter::quizChoices();
            $currentKind = Moncine\ContentKindFilter::normalize((string) ($saved['content_kind'] ?? ''));
            foreach ($kindChoices as $value => $label):
                $chk = $currentKind === $value ? ' checked' : '';
                ?>
                <label class="radio">
                    <input type="radio" name="content_kind" value="<?= Moncine\View::escape($value) ?>"<?= $chk ?>>
                    <?= Moncine\View::escape($label) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <fieldset>
            <legend>3. Décennie</legend>
            <label for="decennie">Période de sortie du film</label>
            <select name="decennie" id="decennie">
                <?php
                $decennies = [
                    '' => 'Peu importe',
                    '2020' => 'Années 2020',
                    '2010' => 'Années 2010',
                    '2000' => 'Années 2000',
                    '1990' => 'Années 1990',
                    '1980' => 'Années 1980',
                    '1970' => 'Années 1970',
                    '1960' => 'Années 1960',
                    'avant1960' => 'Avant 1960',
                ];
                $currentDecennie = (string) ($saved['decennie'] ?? '');
                foreach ($decennies as $val => $label):
                    $sel = $currentDecennie === $val ? ' selected' : '';
                    ?>
                    <option value="<?= Moncine\View::escape($val) ?>"<?= $sel ?>><?= Moncine\View::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Seuls les films avec une année renseignée sont proposés si vous choisissez une décennie (enrichissement ou modification manuelle).</p>
        </fieldset>

        <fieldset>
            <legend>4. Nationalité / pays</legend>
            <p class="hint">
                Cochez un ou plusieurs pays (ex. France, USA). Un film n’a qu’un pays principal. Laissez vide pour tous.
                Seuls les films avec un pays renseigné sont proposés si vous filtrez.
            </p>
            <?php if ($nationaliteChoices === []): ?>
                <p class="alert alert-info">
                    Enrichissez vos fiches via TMDB ou renseignez le pays à la main pour filtrer ici.
                </p>
            <?php else: ?>
                <div class="checkbox-grid">
                    <?php foreach ($nationaliteChoices as $countryLabel):
                        $checked = in_array($countryLabel, $selectedNationalites, true) ? ' checked' : '';
                        ?>
                        <label class="checkbox">
                            <input type="checkbox" name="nationalites[]"
                                   value="<?= Moncine\View::escape($countryLabel) ?>"<?= $checked ?>>
                            <?= Moncine\View::escape($countryLabel) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </fieldset>

        <fieldset>
            <legend>5. Style / ambiance</legend>
            <p class="hint">Cochez un ou plusieurs styles. Laissez vide pour tous les styles.</p>
            <?php if ($styleChoices === []): ?>
                <p class="alert alert-info">Importez d'abord des films avec une colonne « Style » pour filtrer par genre.</p>
            <?php else: ?>
                <div class="checkbox-grid">
                    <?php foreach ($styleChoices as $styleLabel):
                        $checked = in_array($styleLabel, $selectedStyles, true) ? ' checked' : '';
                        ?>
                        <label class="checkbox">
                            <input type="checkbox" name="styles[]" value="<?= Moncine\View::escape($styleLabel) ?>"<?= $checked ?>>
                            <?= Moncine\View::escape($styleLabel) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </fieldset>

        <fieldset>
            <legend>6. Films déjà vus</legend>
            <?php
            $policies = [
                'jamais' => 'Seulement les films jamais vus',
                'ancien_ok' => 'Revu OK si vu il y a plus de 24 mois (2 ans)',
                'peu_importe' => 'Peu importe',
            ];
            $currentPolicy = (string) ($saved['vu_policy'] ?? 'ancien_ok');
            foreach ($policies as $value => $label):
                $chk = $currentPolicy === $value ? ' checked' : '';
                ?>
                <label class="radio">
                    <input type="radio" name="vu_policy" value="<?= Moncine\View::escape($value) ?>"<?= $chk ?>>
                    <?= Moncine\View::escape($label) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <fieldset>
            <legend>7. Format (optionnel)</legend>
            <label for="format_image">Format image</label>
            <input type="text" name="format_image" id="format_image"
                   value="<?= Moncine\View::escape((string) ($saved['format_image'] ?? '')) ?>"
                   placeholder="ex. 2.40:1, Blu-ray…">

            <label for="format_son">Bande sonore</label>
            <input type="text" name="format_son" id="format_son"
                   value="<?= Moncine\View::escape((string) ($saved['format_son'] ?? '')) ?>"
                   placeholder="ex. 5.1 DTS-HD, Atmos…">
        </fieldset>

        <button type="submit" class="btn btn-primary btn-lg">Trouver un film</button>
    </form>
</section>
