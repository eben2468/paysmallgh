<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Every SMS the platform sends, in one place. Keep each under 160 characters.
 * Voice: a Ghanaian talking to their cousin. Warm, short, direct.
 */
final class SmsTemplates
{
    public static function receipt(string $product, int $number, int $total, string $amountGhs, string $leftGhs): string
    {
        if ($leftGhs === 'GHS 0') {
            return "Payment {$number}/{$total} received ({$amountGhs}). That's everything paid on your {$product}!";
        }
        return "Got it! {$amountGhs} received for your {$product}. That's {$number}/{$total} paid. {$leftGhs} to go. #PaySmallSmall";
    }

    public static function planStarted(string $product, string $installmentGhs, string $freq, int $count): string
    {
        $per = $freq === 'daily' ? 'a day' : 'a week';
        return "Your plan for {$product} has started! {$installmentGhs} {$per} x {$count}. First payment received. #PaySmallSmall";
    }

    public static function planCompleteCustomer(string $product, string $shop): string
    {
        return "Congrats! Your {$product} is fully paid. Go collect it from {$shop} — show them this SMS. #PaySmallSmall";
    }

    public static function planCompleteMerchant(string $product, string $payoutGhs, string $customer): string
    {
        return "{$customer} finished paying for {$product}. {$payoutGhs} is on its way to your MoMo. Please release the item. #PaySmallSmall";
    }

    public static function paymentDueSoon(string $product, string $amountGhs, string $whenLabel): string
    {
        return "Reminder: your {$amountGhs} payment for {$product} is due {$whenLabel}. Pay small small to stay on track. #PaySmallSmall";
    }

    public static function missedPayment(string $amountGhs, string $payByDay): string
    {
        return "Life happens. You missed this week's {$amountGhs} — no penalty yet. Pay by {$payByDay} and you're still on track. #PaySmallSmall";
    }

    public static function planFlaggedMerchant(string $product, string $customer): string
    {
        return "Heads up: {$customer}'s plan for {$product} has stalled past the grace period. We'll keep you posted. #PaySmallSmall";
    }

    public static function refund(string $product, string $refundGhs): string
    {
        return "Your plan for {$product} is cancelled. {$refundGhs} is coming back to your MoMo (small cancellation fee applied). #PaySmallSmall";
    }

    public static function merchantApproved(string $shop): string
    {
        return "{$shop} is live on PaySmallSmall! Add your products and start selling small small. Log in to your dashboard to begin.";
    }
}
