<?php
/**
 * api/proxy.php
 *
 * Server-side proxy that forwards API requests from the browser to the
 * upstream AAS (Seamless) API provider, appending the agent credentials.
 * This avoids any CORS issues on the client side.
 *
 * Expected POST body (JSON):
 *   { "endpoint": "/api/v2/game_list", "payload": { ... } }
 *
 * The proxy injects agent_code and agent_token automatically; the
 * caller does NOT need to include them.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 0, 'msg' => 'METHOD_NOT_ALLOWED']);
    exit;
}

$body = requireJsonBody();

$endpoint = isset($body['endpoint']) ? trim((string)$body['endpoint']) : '';
$payload  = isset($body['payload']) && is_array($body['payload']) ? $body['payload'] : [];

// Handle local-only endpoint for user balance (no upstream call)
if ($endpoint === '/local/user_balance') {
    $userCode = isset($payload['user_code']) ? trim((string)$payload['user_code']) : '';
    if ($userCode === '') {
        http_response_code(400);
        echo json_encode(['status' => 0, 'msg' => 'MISSING_USER_CODE']);
        exit;
    }
    echo json_encode([
        'status'       => 1,
        'user_balance' => getUserBalance($userCode),
    ]);
    exit;
}

// Whitelist of allowed upstream endpoints
$allowed = [
    '/api/v2/game_launch',
    '/api/v2/info',
    '/api/v2/provider_list',
    '/api/v2/game_list',
    '/api/v2/agent_rtp',
    '/api/v2/user_rtp',
];

if (!in_array($endpoint, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['status' => 0, 'msg' => 'INVALID_ENDPOINT']);
    exit;
}

// Inject credentials
$payload['agent_code']  = AGENT_CODE;
$payload['agent_token'] = AGENT_TOKEN;

// If launching a game, also supply the current user balance
if ($endpoint === '/api/v2/game_launch' && isset($payload['user_code'])) {
    $payload['user_balance'] = getUserBalance((string)$payload['user_code']);
}

$url = rtrim(AAS_BASE_URL, '/') . $endpoint;

$ch = curl_init($url);
if ($ch === false) {
    http_response_code(502);
    echo json_encode(['status' => 0, 'msg' => 'CURL_INIT_FAILED']);
    exit;
}

curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false || $curlErr) {
    http_response_code(502);
    echo json_encode(['status' => 0, 'msg' => 'UPSTREAM_ERROR', 'detail' => $curlErr]);
    exit;
}

http_response_code($httpCode ?: 200);
echo $response;
