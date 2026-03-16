<?php
/**
 * gold_api/game_callback.php
 *
 * Seamless Callback API — Game History Callback [Essential]
 *
 * Called by the game provider at the end of every game round to debit/credit
 * the player's balance.
 *
 * Supported txn_type values:
 *   "debit"        — subtract bet from balance
 *   "credit"       — add win to balance
 *   "debit_credit" — subtract bet then add win atomically
 *
 * Expected POST body (JSON):
 *   {
 *     "agent_code":        "luckyluked",
 *     "agent_secret":      "<secret>",
 *     "user_code":         "test",
 *     "slot": {
 *       "txn_type":              "debit_credit",
 *       "bet":                   1000,
 *       "win":                   200,
 *       "txn_id":                "MVGKE8FJE3838EFN378DF",
 *       "provider_code":         "BOOONGO",
 *       "game_code":             "sun_of_egypt",
 *       "round_id":              1700000000001,
 *       "type":                  "BASE",
 *       "user_before_balance":   1741708,
 *       "user_after_balance":    1739708,
 *       ...
 *     }
 *   }
 *
 * Success response:
 *   { "status": 1, "user_balance": <new balance> }
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

// Validate required top-level fields
if (empty($body['agent_secret']) || empty($body['user_code']) || empty($body['slot'])) {
    http_response_code(400);
    echo json_encode(['status' => 0, 'user_balance' => 0, 'msg' => 'INVALID_PARAMETER']);
    exit;
}

validateSecret((string)$body['agent_secret']);

$userCode = (string)$body['user_code'];
$slot     = (array)$body['slot'];

if (empty($slot['txn_type'])) {
    http_response_code(400);
    echo json_encode(['status' => 0, 'user_balance' => 0, 'msg' => 'MISSING_TXN_TYPE']);
    exit;
}

$txnType = strtolower((string)$slot['txn_type']);
$bet     = isset($slot['bet']) ? (int)$slot['bet'] : 0;
$win     = isset($slot['win']) ? (int)$slot['win'] : 0;

$balance = getUserBalance($userCode);

switch ($txnType) {
    case 'debit':
        if ($balance < $bet) {
            echo json_encode(['status' => 0, 'user_balance' => $balance, 'msg' => 'INSUFFICIENT_USER_FUNDS']);
            exit;
        }
        $balance -= $bet;
        break;

    case 'credit':
        $balance += $win;
        break;

    case 'debit_credit':
        if ($balance < $bet) {
            echo json_encode(['status' => 0, 'user_balance' => $balance, 'msg' => 'INSUFFICIENT_USER_FUNDS']);
            exit;
        }
        $balance = $balance - $bet + $win;
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 0, 'user_balance' => 0, 'msg' => 'INVALID_TXN_TYPE']);
        exit;
}

setUserBalance($userCode, $balance);

echo json_encode([
    'status'       => 1,
    'user_balance' => $balance,
]);
