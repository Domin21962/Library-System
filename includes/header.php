<?php // Shared page head, included at the top of every page: loads security helpers (session, headers, CSRF),
// then opens <html>, <head> and <body>. Access: include only, no inputs.
// The including page sets $pageTitle first; it is used for the browser tab title.
// Stylesheets: base style, book theme and page-specific styles. Tables: none.
require_once __DIR__ . '/security.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?? 'WebShelf' ?></title>
<link rel="icon" type="image/png" href="<?= APP_BASE ?>/assets/img/favicon.png">
<!-- Styles: base layout, then the book color theme, then page-specific tweaks (later files override earlier ones) -->
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . "/../assets/css/style.css") ?: 1 ?>">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/book-theme.css?v=<?= @filemtime(__DIR__ . "/../assets/css/book-theme.css") ?: 1 ?>">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/pages.css?v=<?= @filemtime(__DIR__ . "/../assets/css/pages.css") ?: 1 ?>">
<?php foreach (($extraCss ?? []) as $css): /* optional page-only stylesheet, e.g. landing.css */ ?>
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/<?= htmlspecialchars($css) ?>?v=<?= @filemtime(__DIR__ . "/../assets/css/" . $css) ?: 1 ?>">
<?php endforeach; ?>

</head>
<body>