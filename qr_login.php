<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
enforceCsrfOnPost();

// QR login uses a short-lived, one-time token. Live camera scanning in a
// browser requires a secure context (HTTPS) on phones. A native camera/photo
// fallback is still provided for local HTTP development.
$TOKEN_EXPIRY_SECONDS = 60;
$error = '';
$success = '';
$tokenUrl = '';

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

if (isset($_GET['token']) && $error === '') {
    $rawToken = strtolower(trim((string)$_GET['token']));

    if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
        $error = 'This QR login code is invalid.';
    } else {
        $hashToken = hash('sha256', $rawToken);
        $stmt = $pdo->prepare(
            "SELECT id, username, token, expires_at, used
             FROM qr_login_tokens
             WHERE token = ? OR token = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$hashToken, $rawToken]);
        $qr = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$qr) {
            $error = 'This QR login code was not found. Generate a new QR code.';
        } else {
            // Use PHP's clock consistently for creation and validation so a
            // MySQL/Laragon timezone mismatch cannot reject a fresh QR code.
            $now = date('Y-m-d H:i:s');

            if ((int)$qr['used'] === 1) {
                $error = 'This QR login code has already been used. Generate a new one.';
            } elseif ((string)$qr['expires_at'] <= $now) {
                $pdo->prepare("UPDATE qr_login_tokens SET used = 1 WHERE id = ? AND used = 0")
                    ->execute([$qr['id']]);
                $error = 'This QR login code has expired. Generate a new one.';
            } else {
                // Atomic one-time consumption prevents two devices from using
                // the same token at the same time.
                $consume = $pdo->prepare(
                    "UPDATE qr_login_tokens
                     SET used = 1
                     WHERE id = ? AND used = 0 AND expires_at > ?"
                );
                $consume->execute([$qr['id'], $now]);

                if ($consume->rowCount() !== 1) {
                    $error = 'This QR login code is no longer valid. Generate a new one.';
                } else {
                    session_regenerate_id(true);
                    auditLog($pdo, 'qr_login_success', $qr['username']);
                    $_SESSION['username'] = $qr['username'];
                    $_SESSION['role'] = getUserRole($qr['username']);

                    $role = $_SESSION['role'];
                    if ($role === 'Admin') {
                        redirect('admin.php');
                    } elseif ($role === 'Teacher') {
                        redirect('teacher.php');
                    } else {
                        redirect('main.php');
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'generate') {
    requireLogin();

    if ($error === '') {
        $username = $_SESSION['username'];

        $pdo->prepare(
            "UPDATE qr_login_tokens SET used = 1 WHERE username = ? AND used = 0"
        )->execute([$username]);

        $rawToken = bin2hex(random_bytes(32));
        $hashToken = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + $TOKEN_EXPIRY_SECONDS);

        $insert = $pdo->prepare(
            "INSERT INTO qr_login_tokens (username, token, expires_at, used)
             VALUES (?, ?, ?, 0)"
        );
        $insert->execute([$username, $hashToken, $expiresAt]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        $tokenUrl = $scheme . '://' . $host . $basePath . '/qr_login.php?token=' . rawurlencode($rawToken);
        $success = 'QR code created. It expires in 60 seconds and can only be used once.';
    }
}

$pageTitle = 'QR Login - Doms Library';
include __DIR__ . '/includes/header.php';
?>
<?php if (!empty($_SESSION['username'])): ?>
<div class="topbar">
    <div class="brand">📚 Doms Library <span class="badge"><?= htmlspecialchars($_SESSION['username']) ?> · <?= htmlspecialchars($_SESSION['role'] ?? '') ?></span></div>
    <button class="menu-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="qrNav">☰</button>
    <nav id="qrNav">
        <a href="main.php">Catalog</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>
<?php endif; ?>

<div class="auth-wrapper">
    <div class="card qr-login-card" style="max-width:460px;text-align:center;">
        <div class="qr-icon" aria-hidden="true">▣</div>
        <h2><?= !empty($_SESSION['username']) ? 'Generate Login QR' : 'Login with QR Code' ?></h2>
        <div class="subtitle">
            <?php if (!empty($_SESSION['username'])): ?>
                Create a temporary QR code that can sign in to this account on another device.
            <?php else: ?>
                Scan a QR code generated from a device that is already logged in to your Doms Library account.
            <?php endif; ?>
        </div>

        <?php if ($error): ?><div class="error" style="text-align:left;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success" style="text-align:left;"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if (!empty($_SESSION['username'])): ?>
            <?php if ($tokenUrl): ?>
                <div id="qr-code" style="display:flex;justify-content:center;align-items:center;background:#fff;padding:18px;border-radius:12px;margin:20px auto;width:max-content;max-width:100%;"></div>
                <div id="qr-countdown" style="font-weight:700;color:var(--accent);margin-top:12px;">Expires in 60s</div>
                <div style="color:var(--muted);font-size:12px;margin-top:10px;line-height:1.5;">
                    Scan this QR with the other device. Keep this page open until the scan is complete.
                </div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="generate">
                <button type="submit" style="width:100%;"><?= $tokenUrl ? 'Generate New QR Code' : 'Generate QR Code' ?></button>
            </form>
            <a class="btn secondary" href="main.php" style="width:100%;margin-top:10px;text-align:center;">Back to Catalog</a>
        <?php else: ?>
            <div id="secure-warning" class="error qr-warning" style="display:none;text-align:left;"></div>

            <div id="reader" class="qr-reader" aria-label="QR camera scanner"></div>
            <div id="scan-status" class="subtitle qr-status" style="margin:8px 0 0;">Starting camera…</div>

            <div class="qr-actions">
                <button type="button" id="start-camera" class="qr-camera-btn">📷 Start Camera</button>
                <button type="button" id="switch-camera" class="btn secondary" style="display:none;">🔄 Switch Camera</button>
            </div>

            <div class="qr-divider"><span>Camera not working?</span></div>

            <label class="qr-file-label" for="qr-image-input">📱 Scan a QR image with your phone camera</label>
            <input id="qr-image-input" class="qr-file-input" type="file" accept="image/*" capture="environment">
            <div class="qr-help">On Android, this opens the phone camera/file picker. Take a clear photo of the QR code and Doms Library will read it.</div>

            <a class="btn secondary" href="login.php" style="width:100%;margin-top:16px;text-align:center;">Use Username & Password</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($tokenUrl): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
const qrTarget = document.getElementById('qr-code');
if (qrTarget && window.QRCode) {
    new QRCode(qrTarget, {
        text: <?= json_encode($tokenUrl) ?>,
        width: 260,
        height: 260,
        correctLevel: QRCode.CorrectLevel.M
    });
}
let seconds = 60;
const countdown = document.getElementById('qr-countdown');
const timer = setInterval(() => {
    seconds--;
    if (countdown) countdown.textContent = seconds > 0 ? `Expires in ${seconds}s` : 'QR expired — generate a new one.';
    if (seconds <= 0) clearInterval(timer);
}, 1000);
</script>
<?php elseif (empty($_SESSION['username']) && !$error): ?>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
(() => {
    const status = document.getElementById('scan-status');
    const warning = document.getElementById('secure-warning');
    const reader = document.getElementById('reader');
    const startButton = document.getElementById('start-camera');
    const switchButton = document.getElementById('switch-camera');
    const imageInput = document.getElementById('qr-image-input');

    let scanner = null;
    let cameras = [];
    let cameraIndex = 0;
    let running = false;
    let handled = false;

    function setStatus(message) {
        if (status) status.textContent = message;
    }

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

    function handleDecoded(decodedText) {
        if (!decodedText || handled) return;

        if (!isValidDomsQr(decodedText)) {
            setStatus('That is not a valid Doms Library login QR code.');
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

    function onScanSuccess(decodedText) {
        handleDecoded(decodedText);
    }

    function onScanError() {
        // html5-qrcode calls this repeatedly while searching; don't show errors
        // for every frame because that makes the UI flicker.
    }

    async function stopScanner() {
        if (!scanner || !running) return;
        try { await scanner.stop(); } catch (e) {}
        running = false;
    }

    async function startScanner() {
        if (handled) return;

        if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            warning.style.display = 'block';
            warning.innerHTML = '<strong>Live camera scanning needs HTTPS.</strong><br>This page is currently using HTTP. Open Doms Library over <strong>HTTPS</strong> on your phone, or use the camera/photo option below.';
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
            setStatus('Camera ready. Point it at the Doms Library QR code.');
        } catch (e) {
            running = false;
            warning.style.display = 'block';
            warning.innerHTML = '<strong>Camera access was blocked or unavailable.</strong><br>Allow camera permission for this site, make sure no other app is using the camera, or use the camera/photo option below.';
            setStatus('Camera could not be started.');
        }
    }

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
            setStatus('Camera switched. Point it at the Doms Library QR code.');
        } catch (e) {
            setStatus('Could not switch camera.');
        }
    }

    async function scanImage(file) {
        if (!file || handled) return;
        setStatus('Reading QR image…');

        try {
            if (!scanner) scanner = new Html5Qrcode('reader', { verbose: false });
            const decodedText = await scanner.scanFile(file, true);
            handleDecoded(decodedText);
        } catch (e) {
            setStatus('No readable Doms Library QR code was found in that image. Try again with the full QR visible and in focus.');
        }
    }

    startButton.addEventListener('click', startScanner);
    switchButton.addEventListener('click', switchCamera);
    imageInput.addEventListener('change', () => {
        const file = imageInput.files && imageInput.files[0];
        scanImage(file);
        imageInput.value = '';
    });

    // Don't request camera permission automatically. Mobile browsers handle
    // an explicit user tap more reliably and it makes the permission flow clear.
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
