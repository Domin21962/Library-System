<?php
/**
 * Minimal dependency-free SMTP client for sending real emails.
 * No Composer / PHPMailer install required - just fill in config/mail.php.
 *
 * sendLibraryEmail() is safe to call anywhere: if mail isn't configured yet
 * (config/mail.php 'enabled' => false) or sending fails, it silently returns
 * false instead of breaking the page that called it.
 */

function sendLibraryEmail(string $to, string $subject, string $body, bool $isHtml = false): bool {
    $config = require __DIR__ . '/../config/mail.php';

    if (empty($config['enabled'])) {
        return false; // mail not configured yet - no-op
    }

    try {
        return smtpSend($config, $to, $subject, $body, $isHtml);
    } catch (Throwable $e) {
        error_log('sendLibraryEmail failed: ' . $e->getMessage());
        return false;
    }
}

function smtpSend(array $config, string $to, string $subject, string $body, bool $isHtml = false): bool {
    $host = $config['host'];
    $port = (int)$config['port'];
    $encryption = $config['encryption'] ?? 'tls';
    $username = $config['username'];
    $password = $config['password'];
    $fromEmail = $config['from_email'];
    $fromName = $config['from_name'] ?? 'Doms Library';

    $transport = ($encryption === 'ssl') ? 'ssl://' : '';
    $socket = @stream_socket_client(
        "$transport$host:$port",
        $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]])
    );

    if (!$socket) {
        error_log("SMTP connect failed: $errstr ($errno)");
        return false;
    }

    $read = function () use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };

    $write = function (string $cmd) use ($socket) {
        fwrite($socket, $cmd . "\r\n");
    };

    $expect = function (string $response, array $codes) {
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new Exception("SMTP unexpected response: $response");
        }
    };

    $read(); // greeting

    $write("EHLO localhost");
    $expect($read(), [250]);

    if ($encryption === 'tls') {
        $write("STARTTLS");
        $expect($read(), [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            throw new Exception("STARTTLS negotiation failed");
        }
        $write("EHLO localhost");
        $expect($read(), [250]);
    }

    $write("AUTH LOGIN");
    $expect($read(), [334]);
    $write(base64_encode($username));
    $expect($read(), [334]);
    $write(base64_encode($password));
    $expect($read(), [235]);

    $write("MAIL FROM:<$fromEmail>");
    $expect($read(), [250]);
    $write("RCPT TO:<$to>");
    $expect($read(), [250, 251]);
    $write("DATA");
    $expect($read(), [354]);

    $headers = [
        "From: $fromName <$fromEmail>",
        "To: <$to>",
        "Subject: " . $subject,
        "MIME-Version: 1.0",
        "Content-Type: " . ($isHtml ? "text/html; charset=UTF-8" : "text/plain; charset=UTF-8"),
        "Date: " . date('r'),
    ];

    // Dot-stuff any line starting with a lone "." per SMTP spec
    $escapedBody = preg_replace('/^\./m', '..', $body);

    $write(implode("\r\n", $headers) . "\r\n\r\n" . $escapedBody . "\r\n.");
    $expect($read(), [250]);

    $write("QUIT");
    fclose($socket);

    return true;
}

/**
 * Builds a clean, receipt-styled HTML email for a borrow or return transaction.
 */
function buildReceiptEmail(string $type, array $data): string {
    // $type is 'borrow' or 'return'
    $isBorrow = $type === 'borrow';
    $heading = $isBorrow ? 'Borrow Receipt' : 'Return Receipt';
    $statusLabel = $isBorrow ? 'BORROWED' : 'RETURNED';
    $statusColor = $isBorrow ? '#2563eb' : '#059669';

    $rows = '';
    $rows .= receiptRow('Transaction ID', '#' . str_pad((string)$data['transaction_id'], 6, '0', STR_PAD_LEFT));
    $rows .= receiptRow('Book', $data['title']);
    $rows .= receiptRow('Book ID', '#' . $data['book_id']);
    $rows .= receiptRow('Borrower', $data['username']);
    $rows .= receiptRow('Email', $data['email']);
    $rows .= receiptRow('Borrowed On', $data['borrow_date']);
    if ($isBorrow) {
        $rows .= receiptRow('Due Date', $data['due_date']);
    } else {
        $rows .= receiptRow('Returned On', $data['return_date']);
    }

    $html = '
<!DOCTYPE html>
<html>
<body style="margin:0;padding:24px;background:#f2f4f7;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center">
      <table role="presentation" width="420" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border:1px solid #e2e5ea;border-radius:10px;overflow:hidden;">
        <tr>
          <td style="background:#111827;color:#ffffff;padding:20px 24px;">
            <div style="font-size:13px;letter-spacing:1px;opacity:0.7;">DOMS LIBRARY</div>
            <div style="font-size:20px;font-weight:bold;margin-top:4px;">' . htmlspecialchars($heading) . '</div>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 24px 4px;">
            <span style="display:inline-block;background:' . $statusColor . ';color:#fff;font-size:11px;
                         font-weight:bold;letter-spacing:0.5px;padding:4px 10px;border-radius:999px;">
              ' . $statusLabel . '
            </span>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 24px 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="border-top:1px dashed #d7dbe2;margin-top:8px;">
              ' . $rows . '
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 24px;background:#f9fafb;border-top:1px dashed #d7dbe2;
                     font-size:12px;color:#6b7280;text-align:center;">
            This is an automated receipt from the Doms Library.<br>Please keep it for your records.
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';

    return $html;
}

function receiptRow(string $label, string $value): string {
    return '
    <tr>
      <td style="padding:8px 0;font-size:13px;color:#6b7280;border-bottom:1px dashed #eceef1;">' . htmlspecialchars($label) . '</td>
      <td style="padding:8px 0;font-size:13px;color:#111827;font-weight:600;text-align:right;border-bottom:1px dashed #eceef1;">' . htmlspecialchars($value) . '</td>
    </tr>';
}

// Password-change confirmation code email
function buildPasswordCodeEmail(string $username, string $code, int $expiresInMinutes): string {
    return '
<!DOCTYPE html>
<html>
<body style="margin:0;padding:24px;background:#f2f4f7;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center">
      <table role="presentation" width="420" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border:1px solid #e2e5ea;border-radius:10px;overflow:hidden;">
        <tr>
          <td style="background:#111827;color:#ffffff;padding:20px 24px;">
            <div style="font-size:13px;letter-spacing:1px;opacity:0.7;">DOMS LIBRARY</div>
            <div style="font-size:20px;font-weight:bold;margin-top:4px;">Confirm your password change</div>
          </td>
        </tr>
        <tr>
          <td style="padding:24px;">
            <p style="margin:0 0 16px;font-size:14px;color:#374151;">
              We received a request to change the password for account
              <strong>' . htmlspecialchars($username) . '</strong>. Enter this code on the
              password change screen to confirm it:
            </p>
            <div style="text-align:center;margin:24px 0;">
              <span style="display:inline-block;font-size:32px;font-weight:bold;letter-spacing:8px;
                           color:#111827;background:#f3f4f6;padding:14px 20px;border-radius:8px;">
                ' . htmlspecialchars($code) . '
              </span>
            </div>
            <p style="margin:0;font-size:13px;color:#6b7280;">
              This code expires in ' . (int)$expiresInMinutes . ' minutes. If you didn\'t request
              this, you can safely ignore this email — your password won\'t be changed.
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 24px;background:#f9fafb;border-top:1px dashed #d7dbe2;
                     font-size:12px;color:#6b7280;text-align:center;">
            This is an automated message from the Doms Library. Please don\'t reply to it.
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';
}


function buildLoginConfirmationEmail(string $username, string $code, int $expiresInMinutes): string {
    $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    return "<!doctype html><html><body style=\"font-family:Arial,sans-serif;background:#f4f6fb;padding:24px\"><div style=\"max-width:480px;margin:auto;background:#fff;padding:28px;border-radius:12px\"><h2>Doms Library login confirmation</h2><p>Hi {$safeUser},</p><p>Someone just tried to sign in to your account. <strong>Is this you?</strong></p><p>If it was you, enter this one-time code:</p><div style=\"font-size:32px;font-weight:700;letter-spacing:8px;text-align:center;padding:18px;background:#eef2ff;border-radius:8px\">{$safeCode}</div><p>This code expires in {$expiresInMinutes} minutes and can only be used once.</p><p>If you did not try to sign in, change your password and contact a library administrator.</p></div></body></html>";
}


function buildUsernameRecoveryEmail(array $usernames): string {
    $items = '';
    foreach ($usernames as $username) {
        $items .= '<li style="margin:6px 0;">' . htmlspecialchars((string)$username, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f2f4f7;padding:24px;"><div style="max-width:420px;margin:auto;background:#fff;padding:24px;border-radius:10px;"><h2>Doms Library username reminder</h2><p>We received a request to find the username(s) connected to this email address.</p><ul>' . $items . '</ul><p>If you did not request this, you can ignore this email.</p></div></body></html>';
}

function buildPasswordResetEmail(string $username, string $resetUrl, int $expiresMinutes = 15): string {
    return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f2f4f7;padding:24px;"><div style="max-width:420px;margin:auto;background:#fff;padding:24px;border-radius:10px;"><h2>Reset your Doms Library password</h2><p>Hello <strong>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong>,</p><p>Click the button below to create a new password. This link expires in ' . (int)$expiresMinutes . ' minutes.</p><p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 18px;background:#5b8cff;color:#fff;text-decoration:none;border-radius:8px;">Reset password</a></p><p>If you did not request this, ignore this email.</p></div></body></html>';
}
