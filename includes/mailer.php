<?php
// ============================================================
// includes/mailer.php  — Sends emails via Gmail SMTP (PHPMailer)
// ============================================================

require_once __DIR__ . '/../vendor/autoload.php'; // composer require phpmailer/phpmailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Gmail credentials ───────────────────────────────────────
// Use an App Password, NOT your real Gmail password.
// Generate one at: https://myaccount.google.com/apppasswords
define('GMAIL_ADDRESS',      'jeivirodelas8@gmail.com');
define('GMAIL_APP_PASSWORD', 'xmmy igda ukco cdbn'); // 16-char app password
define('MAIL_FROM_NAME',     'Barangay San Isidro');
/**
 * Send a password reset email with a one-time link.
 * Returns true on success, false on failure.
 */
function sendPasswordResetEmail(string $toEmail, string $toName, string $resetLink): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_ADDRESS;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(GMAIL_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Reset your password — ' . MAIL_FROM_NAME;
        $mail->Body    = "
            <p>Hi " . htmlspecialchars($toName) . ",</p>
            <p>We received a request to reset your password. Click the button below to choose a new one. This link expires in 30 minutes.</p>
            <p><a href='" . htmlspecialchars($resetLink) . "'
                 style='background:#0066cc;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>
                 Reset Password
            </a></p>
            <p>If you didn't request this, you can safely ignore this email.</p>
        ";
        $mail->AltBody = "Reset your password using this link: $resetLink (expires in 30 minutes)";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Generate a secure reset token, store it, and return it.
 */
function createPasswordResetToken(string $userType, int $userId): string {
    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // Invalidate any previous unused tokens for this user
    dbExecute(
        "UPDATE password_resets SET used=1 WHERE user_type=? AND user_id=? AND used=0",
        [$userType, $userId]
    );

    dbInsert(
        "INSERT INTO password_resets (user_type, user_id, token, expires_at) VALUES (?,?,?,?)",
        [$userType, $userId, $token, $expiresAt]
    );

    return $token;
}

/**
 * Validate a token. Returns the reset row if valid & unexpired, else null.
 * Expiry is compared using PHP's clock (not MySQL's NOW()) to avoid
 * timezone mismatches between the PHP server and the MySQL server.
 */
function validatePasswordResetToken(string $token): ?array {
    $row = dbFetchOne(
        "SELECT * FROM password_resets WHERE token=? AND used=0 LIMIT 1",
        [$token]
    );
    if (!$row) return null;

    // Compare expiry using PHP's own timestamp for both sides
    if (strtotime($row['expires_at']) < time()) {
        return null; // expired
    }
    return $row;
}

function markResetTokenUsed(int $resetId): void {
    dbExecute("UPDATE password_resets SET used=1 WHERE id=?", [$resetId]);
}