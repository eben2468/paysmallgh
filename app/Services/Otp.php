<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database as DB;

/**
 * One-time SMS codes: confirm a number, reset a PIN/password, change number.
 *
 * - 6 digits, valid 10 minutes, 5 wrong tries and it's dead.
 * - Only a hash is stored; the SMS log gets a masked copy.
 * - At most one new code a minute and 5 an hour per number and purpose,
 *   so nobody can run up an SMS bill or spam a phone.
 * - When SMS is in mock mode (demo), the latest code is kept in the session
 *   so the page can show it — there's no real phone to read it from.
 */
final class Otp
{
    public const PURPOSES = [
        'verify' => 'confirm your number',
        'reset' => 'reset your PIN',
        'merchant_reset' => 'reset your shop password',
        'change_phone' => 'confirm your new number',
    ];
    private const TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const RESEND_SECONDS = 60;
    private const MAX_PER_HOUR = 5;

    /**
     * Send a fresh code. Returns ['ok' => bool, 'wait' => seconds before
     * another send is allowed, 'message' => text for the customer when not ok].
     */
    public static function send(string $phone, string $purpose): array
    {
        if (!isset(self::PURPOSES[$purpose])) {
            return ['ok' => false, 'wait' => 0, 'message' => 'Something went wrong. Try again.'];
        }

        $last = DB::run(
            'SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age FROM otp_codes
             WHERE phone = ? AND purpose = ? ORDER BY id DESC LIMIT 1',
            [$phone, $purpose]
        )->fetch();
        if ($last && (int) $last['age'] < self::RESEND_SECONDS) {
            $wait = self::RESEND_SECONDS - (int) $last['age'];
            return ['ok' => false, 'wait' => $wait, 'message' => "We just sent a code. Wait {$wait} seconds before asking for another."];
        }
        $recent = (int) DB::run(
            'SELECT COUNT(*) FROM otp_codes WHERE phone = ? AND purpose = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            [$phone, $purpose]
        )->fetchColumn();
        if ($recent >= self::MAX_PER_HOUR) {
            return ['ok' => false, 'wait' => 0, 'message' => 'Too many codes for this number. Try again in an hour.'];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // Any older code for the same thing stops working.
        DB::run('UPDATE otp_codes SET used_at = NOW() WHERE phone = ? AND purpose = ? AND used_at IS NULL', [$phone, $purpose]);
        DB::run(
            'INSERT INTO otp_codes (phone, purpose, code_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . self::TTL_MINUTES . ' MINUTE))',
            [$phone, $purpose, password_hash($code, PASSWORD_DEFAULT)]
        );

        $sms = new SmsService();
        $what = self::PURPOSES[$purpose];
        $sms->send($phone, SmsTemplates::oneTimeCode($code, $what), false, SmsTemplates::oneTimeCode('******', $what));
        if (!$sms->isLive()) {
            $_SESSION['otp_demo'][$purpose] = $code;
        }
        return ['ok' => true, 'wait' => self::RESEND_SECONDS, 'message' => ''];
    }

    /**
     * Check a typed code. Returns 'ok' | 'wrong' | 'expired' | 'locked'.
     * A correct code is used up; a wrong one counts against its 5 tries.
     */
    public static function check(string $phone, string $purpose, string $code): string
    {
        $row = DB::run(
            'SELECT id, code_hash, attempts, expires_at < NOW() AS expired FROM otp_codes
             WHERE phone = ? AND purpose = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1',
            [$phone, $purpose]
        )->fetch();
        if (!$row || (int) $row['expired'] === 1) {
            return 'expired';
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return 'locked';
        }
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6 || !password_verify($code, $row['code_hash'])) {
            DB::run('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            return (int) $row['attempts'] + 1 >= self::MAX_ATTEMPTS ? 'locked' : 'wrong';
        }
        // Claim it atomically so the same code can't be used twice.
        $claimed = DB::run('UPDATE otp_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$row['id']])->rowCount() > 0;
        if ($claimed) {
            unset($_SESSION['otp_demo'][$purpose]);
        }
        return $claimed ? 'ok' : 'expired';
    }

    /** Customer-facing words for a failed check. */
    public static function errorMessage(string $result): string
    {
        return match ($result) {
            'wrong' => 'That code isn\'t right. Check the SMS and try again.',
            'locked' => 'Too many wrong tries. Ask for a new code.',
            default => 'That code has expired. Ask for a new one.',
        };
    }

    /** The code to show on screen in demo (mock SMS) mode, if any. */
    public static function demoCode(string $purpose): ?string
    {
        $c = $_SESSION['otp_demo'][$purpose] ?? null;
        return is_string($c) ? $c : null;
    }
}
