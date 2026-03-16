<?php
/**
 * Seamless API (AAS API) Configuration
 *
 * ⚠️  SECURITY WARNING
 * The fallback credential values below are provided for initial setup only.
 * Before going live you should supply all four values via server environment
 * variables (see below) and remove the fallback strings from this file, or
 * store them in a file that lives outside the web root.
 *
 * Recommended: set these in Hostinger → PHP Config → Environment Variables:
 *   AAS_BASE_URL      — API provider base URL
 *   AAS_AGENT_CODE    — Your agent code
 *   AAS_AGENT_TOKEN   — Your agent token
 *   AAS_AGENT_SECRET  — Callback authentication secret
 */

define('AAS_BASE_URL',    getenv('AAS_BASE_URL')    ?: 'https://api.pplaygame.net');
define('AGENT_CODE',      getenv('AAS_AGENT_CODE')  ?: 'luckyluked');
define('AGENT_TOKEN',     getenv('AAS_AGENT_TOKEN') ?: 'b30d1c53f79ca515146b63c7c3a661df');
define('AGENT_SECRET',    getenv('AAS_AGENT_SECRET')?: 'E0WGhRuRfAo5i9f2pjcZ6xPhcMQB1EEwtWY56pDEAFhvRxmxW2YA7lDtODuozLYi');

/** Path to the JSON file used for simple user-balance persistence. */
define('USERS_FILE',      __DIR__ . '/data/users.json');

/** Default starting balance for newly created users (in cents / smallest currency unit). */
define('DEFAULT_BALANCE', 100000);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Load the users array from disk, creating the file if needed.
 *
 * @return array<string,array{balance:int}>
 */
function loadUsers(): array {
    if (!file_exists(USERS_FILE)) {
        return [];
    }
    $json = file_get_contents(USERS_FILE);
    if ($json === false) {
        return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Persist the users array to disk.
 *
 * @param array<string,array{balance:int}> $users
 */
function saveUsers(array $users): void {
    $dir = dirname(USERS_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Return the balance for $userCode, creating the user with DEFAULT_BALANCE if absent.
 */
function getUserBalance(string $userCode): int {
    $users = loadUsers();
    if (!isset($users[$userCode])) {
        $users[$userCode] = ['balance' => DEFAULT_BALANCE];
        saveUsers($users);
    }
    return (int)$users[$userCode]['balance'];
}

/**
 * Update the balance for $userCode (absolute value, not a delta).
 */
function setUserBalance(string $userCode, int $balance): void {
    $users = loadUsers();
    $users[$userCode] = ['balance' => $balance];
    saveUsers($users);
}

/**
 * Validate that the request contains the expected agent_secret.
 * Sends a JSON error and exits if invalid.
 */
function validateSecret(string $providedSecret): void {
    if (!hash_equals(AGENT_SECRET, $providedSecret)) {
        http_response_code(403);
        echo json_encode(['status' => 0, 'msg' => 'INVALID_SECRET']);
        exit;
    }
}

/**
 * Decode JSON from php://input; exit with error on failure.
 *
 * @return array<string,mixed>
 */
function requireJsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        http_response_code(400);
        echo json_encode(['status' => 0, 'msg' => 'EMPTY_BODY']);
        exit;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['status' => 0, 'msg' => 'INVALID_JSON']);
        exit;
    }
    return $data;
}
