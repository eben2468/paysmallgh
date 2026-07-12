<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\PlanService;

/**
 * Lightweight, cron-free scheduler. Runs the daily background jobs (payment
 * reminders + pending-payment reconciliation) at most once per calendar day,
 * triggered opportunistically by ordinary web traffic. This keeps SMS reminders
 * fully automatic on shared hosting where no real cron is available.
 *
 * A real cron can still call the same PlanService methods for precise timing —
 * the once-a-day guard makes the two safe to run together.
 */
final class Scheduler
{
    /** Run the day's jobs if they haven't run yet today. Cheap no-op otherwise. */
    public static function runDaily(): void
    {
        if (!Config::bool('AUTO_TASKS', true)) {
            return;
        }

        $marker = BASE_PATH . '/storage/last_daily_run';
        $today = date('Y-m-d');
        $last = is_file($marker) ? trim((string) @file_get_contents($marker)) : '';
        if ($last === $today) {
            return; // already done today
        }

        // Claim the slot up-front so two near-simultaneous requests don't both run.
        $dir = dirname($marker);
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        @file_put_contents($marker, $today, LOCK_EX);

        try {
            $svc = new PlanService();
            $svc->runDueReminders(1);   // "your payment is due today/tomorrow"
            $svc->runReminders();       // overdue grace reminders + merchant flags
            $svc->reconcilePending(30); // settle any payments a webhook missed
        } catch (\Throwable $e) {
            error_log('[scheduler] ' . $e->getMessage());
        }
    }
}
