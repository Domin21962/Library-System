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
<!-- Styles: base layout, then the book color theme, then page-specific tweaks (later files override earlier ones) -->
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/book-theme.css">
<link rel="stylesheet" href="assets/css/pages.css">

</head>
<body>