<?php
// ============================================================
// includes/remember_me.php  — "Remember Me" persistent login
// Uses a selector+validator cookie pair so the real token is
// never stored in plain form in the database or the cookie alone.
// ============================================================

const REMEMBER_COOKIE_NAME = 'remember_me';
const REMEMBER_DAYS        = 30;

/**
 * Issue a new remember-me cookie + DB token for this user.
 * Call this right after a successful login, only if the user
 * checked "Remember me".
 */
function issueRememberToken(string $userType, int $userId): void {
    $selector  = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $validator);
    $expiresTs = time() + (REMEMBER_DAYS * 24 * 60 * 60);

    dbInsert(
        "INSERT INTO remember_tokens (user_type, user_id, selector, validator_hash, expires_at)
         VALUES (?,?,?,?,?)",
        [$userType, $userId, $selector, $hash, date('Y-m-d H:i:s', $expiresTs)]
    );

    setcookie(REMEMBER_COOKIE_NAME, $selector . ':' . $validator, [
        'expires'  => $expiresTs,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true,  // uncomment once your site runs on HTTPS
    ]);
}

/**
 * Clear the remember-me cookie and delete its DB record (used on logout,
 * or whenever a token fails validation).
 */
function clearRememberToken(): void {
    if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
        $selector = $parts[0] ?? '';
        if ($selector) {
            dbExecute("DELETE FROM remember_tokens WHERE selector=?", [$selector]);
        }
    }
    setcookie(REMEMBER_COOKIE_NAME, '', ['expires' => time() - 3600, 'path' => '/']);
    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

/**
 * Try to auto-login this user type ('admin' or 'resident') using the
 * remember-me cookie, if a session isn't already active. Returns true
 * if auto-login succeeded (session vars get set), false otherwise.
 */
function attemptAutoLogin(string $userType): bool {
    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) return false;

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
    if (count($parts) !== 2) return false;
    [$selector, $validator] = $parts;
    if (!$selector || !$validator) return false;

    $row = dbFetchOne(
        "SELECT * FROM remember_tokens WHERE selector=? AND user_type=? LIMIT 1",
        [$selector, $userType]
    );
    if (!$row) return false;

    // Expired? Clean it up.
    if (strtotime($row['expires_at']) < time()) {
        dbExecute("DELETE FROM remember_tokens WHERE id=?", [$row['id']]);
        clearRememberToken();
        return false;
    }

    // Validator must match the stored hash (timing-safe comparison)
    if (!hash_equals($row['validator_hash'], hash('sha256', $validator))) {
        // Possible cookie theft/tampering — wipe this token defensively
        dbExecute("DELETE FROM remember_tokens WHERE id=?", [$row['id']]);
        clearRememberToken();
        return false;
    }

    // Token is valid — log the user back in
    if ($userType === 'admin') {
        $admin = dbFetchOne("SELECT * FROM admin_users WHERE id=? AND status='Active'", [$row['user_id']]);
        if (!$admin) return false;
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_role'] = $admin['role'];
    } else {
        $resident = dbFetchOne("SELECT * FROM residents WHERE id=? AND status!='Inactive'", [$row['user_id']]);
        if (!$resident) return false;
        $_SESSION['resident_id']           = $resident['id'];
        $_SESSION['resident_barangay_id']  = $resident['barangay_id'];
        $_SESSION['resident_name']         = $resident['first_name'] . ' ' . $resident['last_name'];
    }

    // Rotate the token: delete the old one, issue a fresh one.
    // This limits how long a stolen cookie stays useful.
    dbExecute("DELETE FROM remember_tokens WHERE id=?", [$row['id']]);
    issueRememberToken($userType, $row['user_id']);

    return true;
}
