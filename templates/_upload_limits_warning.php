<?php
/** Alertes si le serveur PHP n’accepte pas les envois (PDF magazines, affiches, ZIP). */
foreach (Moncine\UploadLimits::phpLimitsWarnings() as $uploadLimitsWarning): ?>
    <div class="alert alert-danger"><?= $uploadLimitsWarning ?></div>
<?php endforeach; ?>
