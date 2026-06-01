<?php
/** Alerte si le serveur PHP n’accepte pas les PDF magazines (limites trop basses). */
$uploadLimitsWarning = Moncine\UploadLimits::phpLimitsWarning();
if ($uploadLimitsWarning !== ''): ?>
    <div class="alert alert-danger"><?= $uploadLimitsWarning ?></div>
<?php endif; ?>
