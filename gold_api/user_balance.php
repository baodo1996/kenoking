<?php
/**
 * gold_api/user_balance.php
 *
 * Seamless Callback API — Check Member Balance [Essential]
 *
 * The game provider calls this endpoint to retrieve a user's current balance
 * before each game round.
 *
 * Expected POST body (JSON):
 *   {
 *     "agent_code":   "luckyluked",
 *     "agent_secret": "<secret>",
 *     "user_code":    "test"
 *   }
 *
 * Success response:
 *   { "status": 1, "user_balance": 1000 }
 *
 * Failure response:
 *   { "status": 0, "user_balance": 0, "msg": "..." }
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 0, 'user_balance' => 0, 'msg' => 'METHOD_NOT_ALLOWED']);
    exit;
}

$body = requireJsonBody();

// Validate required fields
if (empty($body['agent_secret']) || empty($body['user_code'])) {
    http_response_code(400);
    echo json_encode(['status' => 0, 'user_balance' => 0, 'msg' => 'INVALID_PARAMETER']);
    exit;
}

// Authenticate the provider
validateSecret((string)$body['agent_secret']);

$userCode = (string)$body['user_code'];
$balance  = getUserBalance($userCode);

echo json_encode([
    'status'       => 1,
    'user_balance' => $balance,
]);
