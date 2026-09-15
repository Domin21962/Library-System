<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?? 'Library System' ?></title>
<style>
    :root {
        --bg: #0f1420;
        --panel: #171d2b;
        --panel-2: #1e2536;
        --border: #2a3245;
        --text: #e7ebf3;
        --muted: #9aa4b8;
        --accent: #5b8cff;
        --accent-2: #7c6bff;
        --success: #34d399;
        --danger: #f87171;
        --admin-accent: #f59e0b;
        --admin-accent-2: #ea580c;
        --radius: 12px;
    }
    * { box-sizing: border-box; }
    body {
        background: radial-gradient(1200px 600px at 10% -10%, #1c2438 0%, var(--bg) 55%);
        color: var(--text);
        font-family: 'Segoe UI', Roboto, Arial, sans-serif;
        margin: 0;
        min-height: 100vh;
    }
    a { color: var(--accent); }

    /* Centered auth-style pages (login/register/add/remove/info) */
    .auth-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }
    .card {
        background: var(--panel);
        border: 1px solid var(--border);
        padding: 32px 36px;
        border-radius: var(--radius);
        width: 100%;
        max-width: 380px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.35);
    }
    .card h2 {
        margin: 0 0 4px;
        font-size: 22px;
    }
    .card .subtitle {
        color: var(--muted);
        font-size: 13px;
        margin-bottom: 20px;
    }
    label {
        display: block;
        margin: 14px 0 6px;
        font-size: 13px;
        color: var(--muted);
        font-weight: 600;
    }
    input[type=text], input[type=password], input[type=number], textarea, select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--panel-2);
        color: var(--text);
        font-size: 14px;
        outline: none;
        transition: border-color 0.15s;
    }
    input:focus, textarea:focus, select:focus { border-color: var(--accent); }
    input[readonly] { opacity: 0.7; }
    textarea { min-height: 110px; resize: vertical; }

    button, .btn {
        margin-top: 20px;
        padding: 10px 18px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: #fff;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        transition: filter 0.15s, transform 0.05s;
    }
    button:hover, .btn:hover { filter: brightness(1.1); }
    button:active, .btn:active { transform: translateY(1px); }
    button.secondary, .btn.secondary {
        background: var(--panel-2);
        border: 1px solid var(--border);
    }
    button.danger { background: linear-gradient(135deg, #f87171, #ef4444); }

    .error {
        color: var(--danger);
        font-size: 13px;
        margin-top: 12px;
        background: rgba(248,113,113,0.1);
        border: 1px solid rgba(248,113,113,0.3);
        padding: 8px 10px;
        border-radius: 6px;
    }
    .success {
        color: var(--success);
        font-size: 13px;
        margin-top: 12px;
        background: rgba(52,211,153,0.1);
        border: 1px solid rgba(52,211,153,0.3);
        padding: 8px 10px;
        border-radius: 6px;
    }
    .footer-link {
        margin-top: 20px;
        font-size: 13px;
        color: var(--muted);
    }

    .profile-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        margin: 0 auto 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }
    .profile-divider {
        margin: 22px 0 4px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--muted);
        border-top: 1px solid var(--border);
        padding-top: 16px;
    }
    .profile-divider span {
        text-transform: none;
        font-weight: 400;
        letter-spacing: normal;
    }

    /* App shell (main dashboard) */
    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 24px;
        background: var(--panel);
        border-bottom: 1px solid var(--border);
    }
    .topbar.topbar-admin {
        border-bottom: 2px solid var(--admin-accent);
    }
    .topbar .brand {
        font-weight: 700;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .topbar .brand .badge {
        font-size: 11px;
        font-weight: 600;
        color: var(--muted);
        background: var(--panel-2);
        border: 1px solid var(--border);
        padding: 2px 8px;
        border-radius: 999px;
    }
    .topbar nav a {
        color: var(--text);
        margin-left: 18px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
    }
    .topbar nav a:hover { color: var(--accent); }

    .layout { display: flex; min-height: calc(100vh - 57px); }
    .sidebar {
        width: 200px;
        flex-shrink: 0;
        padding: 20px 12px;
        border-right: 1px solid var(--border);
    }
    .sidebar a {
        display: block;
        padding: 10px 14px;
        color: var(--text);
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        border-radius: 8px;
        margin-bottom: 4px;
    }
    .sidebar a:hover { background: var(--panel-2); }
    .content { flex: 1; padding: 24px 32px; width: 100%; min-width: 0; }

    .search-bar { display: flex; gap: 10px; margin-bottom: 16px; }
    .search-bar input { flex: 1; }
    .search-bar button { margin-top: 0; }

    /* Stats strip */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 14px 18px;
    }
    .stat-value { font-size: 26px; font-weight: 800; }
    .stat-label { font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 2px; }
    .stat-available .stat-value { color: var(--success); }
    .stat-borrowed .stat-value { color: var(--danger); }

    /* Genre filter chips */
    .chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 22px;
    }
    .chip {
        background: var(--panel);
        border: 1px solid var(--border);
        color: var(--text);
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 13px;
        text-decoration: none;
        font-weight: 600;
        transition: background 0.15s, border-color 0.15s;
    }
    .chip:hover { border-color: var(--accent); }
    .chip-active {
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        border-color: transparent;
        color: #fff;
    }

    .book-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 18px;
    }
    .book-card {
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 16px;
        position: relative;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        display: block;
        text-decoration: none;
        color: inherit;
        cursor: pointer;
    }
    .book-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 30px rgba(0,0,0,0.35);
    }
    .read-cta {
        margin-top: 12px;
        font-size: 13px;
        font-weight: 600;
        color: var(--accent);
    }
    .book-card:hover .read-cta { filter: brightness(1.2); }
    .book-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .book-card .book-id {
        font-size: 11px;
        color: var(--muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .badge-status {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 999px;
    }
    .badge-available { color: var(--success); background: rgba(52,211,153,0.12); }
    .badge-borrowed { color: var(--danger); background: rgba(248,113,113,0.12); }
    .genre-icon { font-size: 26px; margin: 6px 0 2px; }
    .book-card h3 {
        margin: 2px 0 2px;
        font-size: 16px;
    }
    .book-card .author {
        color: var(--muted);
        font-size: 13px;
        margin-bottom: 10px;
    }
    .book-card .meta {
        font-size: 12px;
        color: var(--muted);
        margin-bottom: 10px;
    }
    .book-card .tag {
        display: inline-block;
        background: var(--panel-2);
        border: 1px solid var(--border);
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        margin-right: 6px;
    }
    .book-card .content-area {
        background: var(--panel-2);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 10px;
        font-size: 13px;
        max-height: 160px;
        overflow-y: auto;
        white-space: pre-wrap;
        color: var(--text);
    }
    .borrowed-note {
        margin-top: 10px;
        font-size: 12px;
        color: var(--danger);
        font-style: italic;
    }

    table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 14px; }
    th { text-align: left; padding: 10px 8px; color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.03em; border-bottom: 1px solid var(--border); }
    td { padding: 10px 8px; border-bottom: 1px solid var(--border); }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--muted);
    }
    .page-title { font-size: 20px; margin: 0 0 20px; }

    /* Book detail / reader page */
    .reader-wrap { max-width: 820px; margin: 0 auto; padding: 28px 24px 60px; }
    .reader-head {
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 24px;
        margin-bottom: 22px;
    }
    .reader-head h1 { margin: 6px 0 4px; font-size: 26px; }
    .reader-head .author { color: var(--muted); font-size: 15px; margin-bottom: 12px; }
    .reader-synopsis {
        background: var(--panel-2);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 14px;
        font-size: 14px;
        line-height: 1.6;
        margin-top: 14px;
    }
    .reader-body {
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 32px 38px;
        font-size: 16px;
        line-height: 1.8;
        white-space: pre-wrap;
        font-family: Georgia, 'Times New Roman', serif;
        max-height: 70vh;
        overflow-y: auto;
    }
    .reader-note {
        background: var(--panel-2);
        border: 1px solid var(--border);
        border-left: 3px solid var(--accent);
        border-radius: 8px;
        padding: 16px;
        font-size: 14px;
        line-height: 1.6;
        color: var(--muted);
    }
    .reader-note strong { color: var(--text); }
</style>
</head>
<body>
