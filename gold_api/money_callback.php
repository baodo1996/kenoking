<?php
/**
 * gold_api/money_callback.php
 *
 * Seamless Callback API — Amount Change Callback [Optional]
 *
 * Called by the game provider to notify the operator about deposits,
 * withdrawals, or balance adjustments.
 *
 * Expected POST body (JSON):
 *   {
 *     "agent_code":          "luckyluked",
 *     "agent_secret":        "<secret>",
 *     "agent_type":          "Seamless",
 *     "user_code":           "test",
 *     "provider_code":       "PRAGMATIC",
 *     "game_code":           "vs20doghouse",
 *     "type":                "debit",
 *     "agent_before_balance": 110000,
 *     "agent_after_balance":  113000,
 *     "user_before_balance":  10000,
 *     "user_after_balance":   13000,
 *     "amount":               3000,
 *     "msg":                  ""
 *   }
 *
 * Success response:
 *   { "status": 1, "msg": "SUCCESS" }
 *
 * Failure response:
 *   { "status": 0, "msg": "..." }
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 0, 'msg' => 'METHOD_NOT_ALLOWED']);
    exit;
}

$body = requireJsonBody();

if (empty($body['agent_secret']) || empty($body['user_code'])) {
    http_response_code(400);
    echo json_encode(['status' => 0, 'msg' => 'INVALID_PARAMETER']);
    exit;
}

validateSecret((string)$body['agent_secret']);

$userCode       = (string)$body['user_code'];
$userAfterBal   = isset($body['user_after_balance']) ? (int)$body['user_after_balance'] : null;

// Sync the locally stored balance with what the provider reports as the
// authoritative after-balance for this user.
if ($userAfterBal !== null) {
    setUserBalance($userCode, $userAfterBal);
}

echo json_encode(['status' => 1, 'msg' => 'SUCCESS']);
