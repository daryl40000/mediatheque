<?php
/** @var string $templateFile */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= Moncine\View::escape($pageTitle ?? MONCINE_APP_NAME) ?> — <?= Moncine\View::escape(MONCINE_APP_NAME) ?></title>
    <link rel="icon" href="/assets/img/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body class="auth-layout<?= !empty($wideLayout) ? ' page-wide' : '' ?>">
    <header class="auth-layout__brand">
        <?php
        $logoClass = 'logo--auth';
        $logoSize = 72;
        require MONCINE_ROOT . '/templates/_site_logo.php';
        ?>
    </header>
    <main class="container<?= !empty($wideLayout) ? ' container--wide' : ' container--narrow' ?>">
        <?php require $templateFile; ?>
    </main>
</body>
</html>
