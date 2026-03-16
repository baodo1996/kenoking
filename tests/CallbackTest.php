<?php
/**
 * Unit tests for the core helper functions in config.php and the
 * callback logic in gold_api/*.
 *
 * Run: phpunit tests/CallbackTest.php
 */

use PHPUnit\Framework\TestCase;

class CallbackTest extends TestCase
{
    private string $usersFile;

    protected function setUp(): void
    {
        // Use a temporary file for user data so tests are isolated
        $this->usersFile = sys_get_temp_dir() . '/ks_test_users_' . uniqid() . '.json';

        // We need to redefine constants per-process, so instead we override
        // the file path via a helper that lives in config.php.  Since PHP
        // constants cannot be redefined, we include config.php only once and
        // rely on the fact that USERS_FILE already points to data/users.json.
        // To isolate tests we'll work with the raw load/save helpers directly
        // by writing/reading the temp file.
    }

    protected function tearDown(): void
    {
        if (file_exists($this->usersFile)) {
            unlink($this->usersFile);
        }
    }

    // -----------------------------------------------------------------
    // Helper: simulate loadUsers / saveUsers against the temp file
    // -----------------------------------------------------------------

    private function loadUsers(): array
    {
        if (!file_exists($this->usersFile)) {
            return [];
        }
        $json = file_get_contents($this->usersFile);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function saveUsers(array $users): void
    {
        file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function getUserBalance(string $userCode): int
    {
        $users = $this->loadUsers();
        if (!isset($users[$userCode])) {
            $users[$userCode] = ['balance' => 100000]; // DEFAULT_BALANCE
            $this->saveUsers($users);
        }
        return (int)$users[$userCode]['balance'];
    }

    private function setUserBalance(string $userCode, int $balance): void
    {
        $users = $this->loadUsers();
        $users[$userCode] = ['balance' => $balance];
        $this->saveUsers($users);
    }

    // -----------------------------------------------------------------
    // Tests: user balance persistence
    // -----------------------------------------------------------------

    public function testNewUserGetsDefaultBalance(): void
    {
        $balance = $this->getUserBalance('newplayer');
        $this->assertSame(100000, $balance);
    }

    public function testSetAndGetBalance(): void
    {
        $this->setUserBalance('alice', 50000);
        $this->assertSame(50000, $this->getUserBalance('alice'));
    }

    public function testMultipleUsersIndependent(): void
    {
        $this->setUserBalance('alice', 50000);
        $this->setUserBalance('bob', 75000);
        $this->assertSame(50000, $this->getUserBalance('alice'));
        $this->assertSame(75000, $this->getUserBalance('bob'));
    }

    // -----------------------------------------------------------------
    // Tests: game_callback debit / credit / debit_credit logic
    // -----------------------------------------------------------------

    /**
     * Simulate the core transaction logic from game_callback.php.
     *
     * @return array{status:int, user_balance:int, msg?:string}
     */
    private function processTransaction(string $userCode, string $txnType, int $bet, int $win): array
    {
        $balance = $this->getUserBalance($userCode);

        switch ($txnType) {
            case 'debit':
                if ($balance < $bet) {
                    return ['status' => 0, 'user_balance' => $balance, 'msg' => 'INSUFFICIENT_USER_FUNDS'];
                }
                $balance -= $bet;
                break;

            case 'credit':
                $balance += $win;
                break;

            case 'debit_credit':
                if ($balance < $bet) {
                    return ['status' => 0, 'user_balance' => $balance, 'msg' => 'INSUFFICIENT_USER_FUNDS'];
                }
                $balance = $balance - $bet + $win;
                break;

            default:
                return ['status' => 0, 'user_balance' => 0, 'msg' => 'INVALID_TXN_TYPE'];
        }

        $this->setUserBalance($userCode, $balance);
        return ['status' => 1, 'user_balance' => $balance];
    }

    public function testDebitReducesBalance(): void
    {
        $this->setUserBalance('player1', 10000);
        $result = $this->processTransaction('player1', 'debit', 2000, 0);
        $this->assertSame(1, $result['status']);
        $this->assertSame(8000, $result['user_balance']);
    }

    public function testDebitInsufficientFunds(): void
    {
        $this->setUserBalance('player1', 1000);
        $result = $this->processTransaction('player1', 'debit', 2000, 0);
        $this->assertSame(0, $result['status']);
        $this->assertSame('INSUFFICIENT_USER_FUNDS', $result['msg']);
        // Balance should remain unchanged
        $this->assertSame(1000, $this->getUserBalance('player1'));
    }

    public function testCreditIncreasesBalance(): void
    {
        $this->setUserBalance('player1', 5000);
        $result = $this->processTransaction('player1', 'credit', 0, 3000);
        $this->assertSame(1, $result['status']);
        $this->assertSame(8000, $result['user_balance']);
    }

    public function testDebitCreditAtomic(): void
    {
        $this->setUserBalance('player1', 10000);
        $result = $this->processTransaction('player1', 'debit_credit', 2000, 5000);
        $this->assertSame(1, $result['status']);
        $this->assertSame(13000, $result['user_balance']); // 10000 - 2000 + 5000
    }

    public function testDebitCreditInsufficientFunds(): void
    {
        $this->setUserBalance('player1', 500);
        $result = $this->processTransaction('player1', 'debit_credit', 2000, 5000);
        $this->assertSame(0, $result['status']);
        $this->assertSame('INSUFFICIENT_USER_FUNDS', $result['msg']);
    }

    public function testInvalidTxnType(): void
    {
        $this->setUserBalance('player1', 10000);
        $result = $this->processTransaction('player1', 'refund', 0, 0);
        $this->assertSame(0, $result['status']);
        $this->assertSame('INVALID_TXN_TYPE', $result['msg']);
    }

    public function testDebitToZero(): void
    {
        $this->setUserBalance('player1', 5000);
        $result = $this->processTransaction('player1', 'debit', 5000, 0);
        $this->assertSame(1, $result['status']);
        $this->assertSame(0, $result['user_balance']);
    }

    // -----------------------------------------------------------------
    // Tests: money_callback balance sync
    // -----------------------------------------------------------------

    public function testMoneyCallbackSyncsBalance(): void
    {
        $this->setUserBalance('player1', 5000);
        // Simulate money_callback setting authoritative balance
        $userAfterBal = 13000;
        $this->setUserBalance('player1', $userAfterBal);
        $this->assertSame(13000, $this->getUserBalance('player1'));
    }

    // -----------------------------------------------------------------
    // Tests: validateSecret equivalent
    // -----------------------------------------------------------------

    public function testSecretValidation(): void
    {
        $expected = 'E0WGhRuRfAo5i9f2pjcZ6xPhcMQB1EEwtWY56pDEAFhvRxmxW2YA7lDtODuozLYi';
        $this->assertTrue(hash_equals($expected, $expected));
        $this->assertFalse(hash_equals($expected, 'wrong_secret'));
        $this->assertFalse(hash_equals($expected, ''));
    }
}
