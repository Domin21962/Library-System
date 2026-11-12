<?php
// QR login page. Two modes: a logged-in user generates a short-lived QR code; a logged-out visitor scans one to sign in.
// Access: public to scan/sign in; generating a code needs a logged-in session.
// Inputs: GET token (64 hex chars from a scanned QR), POST form=generate, _csrf.
// Tables: qr_login_tokens (created if missing; only SHA-256 hashes of tokens are stored), users via getUserRole().

// ===== Includes =====
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
// Reject POSTs without a valid CSRF token
enforceCsrfOnPost();


// ===== Setup =====
// QR login uses a short-lived, one-time token. Live camera scanning in a
// browser requires a secure context (HTTPS) on phones. A native camera/photo
// fallback is still provided for local HTTP development.
$TOKEN_EXPIRY_SECONDS = 60;
$error = '';
$success = '';
$tokenUrl = '';

// Create the token table on first use so the page works without a manual migration
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_login_tokens (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        token VARCHAR(128) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_qr_username (username, used, expires_at),
        INDEX idx_qr_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    $error = 'QR login is not available because its database table could not be created.';
}


// ===== Sign in with a scanned token (GET) =====
if (isset($_GET['token']) && $error === '') {
    // Normalize the token from the URL
    $rawToken = strtolower(trim((string)$_GET['token']));

    // A valid token is exactly 64 hex characters
    if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
        $error = 'This QR login code is invalid.';
    } else {
        // Look the token up by its hash; the raw token is never stored
        $hashToken = hash('sha256', $rawToken);
        $stmt = $pdo->prepare(
            "SELECT id, username, token, expires_at, used
             FROM qr_login_tokens
             WHERE token = ? 
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$hashToken]);
        $qr = $stmt->fetch(PDO::FETCH_ASSOC);
        // Newest matching row, if any

        if (!$qr) {
            $error = 'This QR login code was not found. Generate a new QR code.';
        } else {
            // Use PHP's clock consistently for creation and validation so a
            // MySQL/Laragon timezone mismatch cannot reject a fresh QR code.
            $now = date('Y-m-d H:i:s');

            // Reject tokens that were already used
            if ((int)$qr['used'] === 1) {
                $error = 'This QR login code has already been used. Generate a new one.';
            } elseif ((string)$qr['expires_at'] <= $now) {
                // Burn the expired token
                $pdo->prepare("UPDATE qr_login_tokens SET used = 1 WHERE id = ? AND used = 0")
                    ->execute([$qr['id']]);
                $error = 'This QR login code has expired. Generate a new one.';
            } else {
                // Atomic one-time consumption prevents two devices from using
                // the same token at the same time.
                // Mark used only if still unused and unexpired; rowCount() tells us whether we won
                $consume = $pdo->prepare(
                    "UPDATE qr_login_tokens
                     SET used = 1
                     WHERE id = ? AND used = 0 AND expires_at > ?"
                );
                $consume->execute([$qr['id'], $now]);

                // Exactly one row changed means this request consumed the token
                if ($consume->rowCount() !== 1) {
                    $error = 'This QR login code is no longer valid. Generate a new one.';
                } else {
                    // Success: new session id prevents session fixation
                    session_regenerate_id(true);
                    // Log the QR sign-in, then start the session for the token's owner
                    auditLog($pdo, 'qr_login_success', $qr['username']);
                    $_SESSION['username'] = $qr['username'];
                    $_SESSION['role'] = getUserRole($qr['username']);

                    // Send each role to its home page
                    $role = $_SESSION['role'];
                    if ($role === 'Admin') {
                        redirect('admin/admin.php');
                    } elseif ($role === 'Teacher') {
                        redirect('teacher/teacher.php');
                    } else {
                        redirect('user/main.php');
                    }
                }
            }
        }
    }
}


// ===== Generate a new QR token (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'generate') {
    // Only logged-in users can create tokens
    requireLogin();

    if ($error === '') {
        $username = $_SESSION['username'];

        $pdo->prepare(
            // Invalidate any earlier unused tokens for this user
            "UPDATE qr_login_tokens SET used = 1 WHERE username = ? AND used = 0"
        )->execute([$username]);

        // 32 random bytes as hex; only the SHA-256 hash is saved in the database
        $rawToken = bin2hex(random_bytes(32));
        $hashToken = hash('sha256', $rawToken);
        // Valid for 60 seconds, using PHP's clock
        $expiresAt = date('Y-m-d H:i:s', time() + $TOKEN_EXPIRY_SECONDS);

        // Store the hash, owner and expiry; the token starts unused
        $insert = $pdo->prepare(
            "INSERT INTO qr_login_tokens (username, token, expires_at, used)
             VALUES (?, ?, ?, 0)"
        );
        $insert->execute([$username, $hashToken, $expiresAt]);

        // Build the full URL the QR code will open on the other device
        $scheme = isHttps() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        $tokenUrl = $scheme . '://' . $host . $basePath . '/qr_login.php?token=' . rawurlencode($rawToken);
        $success = 'QR code created. It expires in 60 seconds and can only be used once.';
    }
}


// ===== Page setup / header =====
$pageTitle = 'QR Login - WebShelf';
include __DIR__ . '/includes/header.php';
?>
<?php if (!empty($_SESSION['username'])): ?>

<!-- ===== HTML output ===== -->
<!-- Top bar (logged-in users only) -->
<div class="topbar">
    <div class="brand">📚 WebShelf <span class="badge"><?= htmlspecialchars($_SESSION['username']) ?> · <?= htmlspecialchars($_SESSION['role'] ?? '') ?></span></div>
    <button class="menu-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="qrNav">☰</button>
    <nav id="qrNav">
        <a href="<?= url('user/main.php') ?>">Catalog</a>
        <a href="<?= url('user/profile.php') ?>">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>
<?php endif; ?>


<!-- Main card: generate view when logged in, scan view otherwise -->
<div class="auth-wrapper">
    <div class="card qr-login-card max-460 text-center">
        
        <div class="qr-icon" aria-hidden="true">▣</div>
        <h2><?= !empty($_SESSION['username']) ? 'Generate Login QR' : 'Login with QR Code' ?></h2>
        <div class="subtitle">
            <?php if (!empty($_SESSION['username'])): ?>
                Create a temporary QR code that can sign in to this account on another device.
            <?php else: ?>
                Scan a QR code generated from a device that is already logged in to your WebShelf account.
            <?php endif; ?>
        </div>

        <!-- Error / success messages -->
        <?php if ($error): ?><div class="error text-left"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success text-left"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <!-- Logged-in view: QR display and generate form -->
        <?php if (!empty($_SESSION['username'])): ?>
            <!-- QR code box and countdown (filled in by the script below) -->
            <?php if ($tokenUrl): ?>
                <div class="qr-code-box" id="qr-code"></div>
                <div class="qr-countdown" id="qr-countdown">Expires in 60s</div>
                <div class="note-small mt-10 lh-15">
                    Scan this QR with the other device. Keep this page open until the scan is complete.
                </div>
            <?php endif; ?>

            <!-- Generate form (CSRF token included) -->
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="generate">
                <button class="w-full" type="submit"><?= $tokenUrl ? 'Generate New QR Code' : 'Generate QR Code' ?></button>
            </form>
            <a class="btn secondary w-full text-center mt-10" href="<?= url('user/main.php') ?>">Back to Catalog</a>
        <!-- Logged-out view: camera scanner and photo fallback -->
        <?php else: ?>
            <!-- Warning shown when the camera needs HTTPS or is blocked -->
            <div id="secure-warning" class="error qr-warning" style="display:none;text-align:left;"></div>

            <!-- Live camera preview and status text -->
            <div id="reader" class="qr-reader" aria-label="QR camera scanner"></div>
            <div id="scan-status" class="subtitle qr-status m-8-0-0">Starting camera…</div>

            <!-- Camera controls -->
            <div class="qr-actions">
                <button type="button" id="start-camera" class="qr-camera-btn">📷 Start Camera</button>
                <button type="button" id="switch-camera" class="btn secondary" style="display:none;">🔄 Switch Camera</button>
            </div>

            <!-- Fallback: scan a photo of the QR code -->
            <div class="qr-divider"><span>Camera not working?</span></div>

            <label class="qr-file-label" for="qr-image-input">📱 Scan a QR image with your phone camera</label>
            <input id="qr-image-input" class="qr-file-input" type="file" accept="image/*" capture="environment">
            <div class="qr-help">On Android, this opens the phone camera/file picker. Take a clear photo of the QR code and WebShelf will read it.</div>

            <a class="btn secondary w-full text-center mt-16" href="login.php">Use Username & Password</a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Scripts ===== -->
<!-- Generated-code script: draws the QR code and runs a 60-second countdown -->
<?php if ($tokenUrl): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
/* Draw the QR code for the sign-in URL (value is JSON-encoded by PHP so it is safe in JS) */
const qrTarget = document.getElementById('qr-code');
if (qrTarget && window.QRCode) {
    new QRCode(qrTarget, {
        text: <?= json_encode($tokenUrl) ?>,
        width: 260,
        height: 260,
        correctLevel: QRCode.CorrectLevel.M
    });
}
/* Countdown matching the 60-second token lifetime; stops at zero */
let seconds = 60;
const countdown = document.getElementById('qr-countdown');
const timer = setInterval(() => {
    seconds--;
    if (countdown) countdown.textContent = seconds > 0 ? `Expires in ${seconds}s` : 'QR expired — generate a new one.';
    if (seconds <= 0) clearInterval(timer);
}, 1000);
</script>
<?php elseif (empty($_SESSION['username']) && !$error): ?>
<!-- Scanner script: camera and image scanning for logged-out visitors -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
/* Scanner module: wrapped in a function so its variables stay private */
(() => {
    /* Page elements the scanner controls */
    const status = document.getElementById('scan-status');
    const warning = document.getElementById('secure-warning');
    const reader = document.getElementById('reader');
    const startButton = document.getElementById('start-camera');
    const switchButton = document.getElementById('switch-camera');
    const imageInput = document.getElementById('qr-image-input');

    /* Scanner state: library instance, camera list, chosen camera, running flag, and a flag that stops double sign-in */
    let scanner = null;
    let cameras = [];
    let cameraIndex = 0;
    let running = false;
    let handled = false;

    /* Show a status message under the camera view */
    function setStatus(message) {
        if (status) status.textContent = message;
    }

    /* True only for a URL ending in /qr_login.php with a 64-hex token, so random QR codes are ignored */
    function isValidDomsQr(decodedText) {
        try {
            const url = new URL(decodedText);
            const path = url.pathname.toLowerCase();
            const token = url.searchParams.get('token') || '';
            return path.endsWith('/qr_login.php') && /^[a-f0-9]{64}$/i.test(token);
        } catch (e) {
            return false;
        }
    }

    /* Validate a decoded QR, stop the camera, then open the sign-in URL (runs once) */
    function handleDecoded(decodedText) {
        if (!decodedText || handled) return;

        if (!isValidDomsQr(decodedText)) {
            setStatus('That is not a valid WebShelf login QR code.');
            return;
        }

        handled = true;
        setStatus('QR detected. Signing you in…');

        if (scanner && running) {
            scanner.stop().catch(() => {}).finally(() => {
                window.location.assign(decodedText);
            });
        } else {
            window.location.assign(decodedText);
        }
    }

    /* Callback from the scanner when a QR code is read */
    function onScanSuccess(decodedText) {
        handleDecoded(decodedText);
    }

    /* Callback for frames with no QR code; intentionally quiet */
    function onScanError() {
        // html5-qrcode calls this repeatedly while searching; don't show errors
        // for every frame because that makes the UI flicker.
    }

    /* Stop the camera if it is running */
    async function stopScanner() {
        if (!scanner || !running) return;
        try { await scanner.stop(); } catch (e) {}
        running = false;
    }

    /* Ask for camera permission (needs HTTPS), pick a camera and start scanning */
    async function startScanner() {
        if (handled) return;

        if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            warning.style.display = 'block';
            warning.innerHTML = '<strong>Live camera scanning needs HTTPS.</strong><br>This page is currently using HTTP. Open WebShelf over <strong>HTTPS</strong> on your phone, or use the camera/photo option below.';
            setStatus('Live camera is unavailable on this HTTP address.');
            return;
        }

        warning.style.display = 'none';
        setStatus('Requesting camera permission…');

        try {
            cameras = await Html5Qrcode.getCameras();
            if (!cameras.length) throw new Error('No camera found');

            // Prefer a rear/environment camera. On Android Chrome, the last
            // enumerated camera is commonly the rear camera, but labels are
            // checked first when available.
            /* Prefer a rear camera, else the last one listed */
            const rearIndex = cameras.findIndex(c => /back|rear|environment/i.test(c.label || ''));
            cameraIndex = rearIndex >= 0 ? rearIndex : Math.max(0, cameras.length - 1);

            if (!scanner) scanner = new Html5Qrcode('reader', { verbose: false });

            await scanner.start(
                cameras[cameraIndex].id,
                { fps: 10, qrbox: { width: 240, height: 240 }, aspectRatio: 1.0 },
                onScanSuccess,
                onScanError
            );

            running = true;
            startButton.style.display = 'none';
            switchButton.style.display = cameras.length > 1 ? 'inline-block' : 'none';
            setStatus('Camera ready. Point it at the WebShelf QR code.');
        } catch (e) {
            running = false;
            warning.style.display = 'block';
            warning.innerHTML = '<strong>Camera access was blocked or unavailable.</strong><br>Allow camera permission for this site, make sure no other app is using the camera, or use the camera/photo option below.';
            setStatus('Camera could not be started.');
        }
    }

    /* Cycle to the next available camera */
    async function switchCamera() {
        if (cameras.length < 2 || !scanner) return;
        await stopScanner();
        cameraIndex = (cameraIndex + 1) % cameras.length;
        try {
            await scanner.start(
                cameras[cameraIndex].id,
                { fps: 10, qrbox: { width: 240, height: 240 }, aspectRatio: 1.0 },
                onScanSuccess,
                onScanError
            );
            running = true;
            setStatus('Camera switched. Point it at the WebShelf QR code.');
        } catch (e) {
            setStatus('Could not switch camera.');
        }
    }

    /* Read a QR code from an uploaded or photographed image file */
    async function scanImage(file) {
        if (!file || handled) return;
        setStatus('Reading QR image…');

        try {
            if (!scanner) scanner = new Html5Qrcode('reader', { verbose: false });
            const decodedText = await scanner.scanFile(file, true);
            handleDecoded(decodedText);
        } catch (e) {
            setStatus('No readable WebShelf QR code was found in that image. Try again with the full QR visible and in focus.');
        }
    }

    /* Wire up buttons and the image picker */
    startButton.addEventListener('click', startScanner);
    switchButton.addEventListener('click', switchCamera);
    imageInput.addEventListener('change', () => {
        const file = imageInput.files && imageInput.files[0];
        scanImage(file);
        imageInput.value = '';
    });

    // Don't request camera permission automatically. Mobile browsers handle
    // an explicit user tap more reliably and it makes the permission flow clear.
    /* Initial hint: camera access is only offered after an explicit tap, and needs HTTPS */
    if (!window.isSecureContext) {
        warning.style.display = 'block';
        warning.innerHTML = '<strong>Live camera scanning needs HTTPS.</strong><br>For your Laragon phone setup, use an HTTPS address. You can still use the camera/photo option below while testing over HTTP.';
        setStatus('Tap Start Camera after opening this page over HTTPS.');
    } else {
        setStatus('Tap “Start Camera” and allow camera access.');
    }
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
