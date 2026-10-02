<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

/**
 * The shopping cart, kept in the session so guests can use it too (sessions
 * last SESSION_LIFETIME_DAYS). Each line is one product + variant + quantity.
 *
 * Checkout is per line: every item becomes its own plan with its own escrow
 * and its own shop payout, so lines are started one at a time from the cart.
 */
final class Cart
{
    private const KEY = 'cart';
    private const MAX_LINES = 20;

    /** Stable id for a product/variant pair. */
    public static function lineKey(int $productId, ?int $variantId): string
    {
        return 'p' . $productId . '-v' . ($variantId ?? 0);
    }

    /** @return array<string, array{product_id:int, variant_id:?int, qty:int}> */
    private static function raw(): array
    {
        $c = $_SESSION[self::KEY] ?? [];
        return is_array($c) ? $c : [];
    }

    /** Number of lines, for the header badge. */
    public static function count(): int
    {
        return count(self::raw());
    }

    /** Add (or top up) a line. Quantity is capped at MAX_QTY. Returns the line key. */
    public static function add(int $productId, ?int $variantId, int $qty): string
    {
        $cart = self::raw();
        $key = self::lineKey($productId, $variantId);
        if (!isset($cart[$key]) && count($cart) >= self::MAX_LINES) {
            array_shift($cart); // drop the oldest line rather than refuse
        }
        $have = (int) ($cart[$key]['qty'] ?? 0);
        $cart[$key] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'qty' => max(1, min(Product::MAX_QTY, $have + $qty)),
        ];
        $_SESSION[self::KEY] = $cart;
        return $key;
    }

    public static function setQty(string $key, int $qty): void
    {
        $cart = self::raw();
        if (!isset($cart[$key])) {
            return;
        }
        if ($qty < 1) {
            unset($cart[$key]);
        } else {
            $cart[$key]['qty'] = min(Product::MAX_QTY, $qty);
        }
        $_SESSION[self::KEY] = $cart;
    }

    public static function remove(string $key): void
    {
        $cart = self::raw();
        unset($cart[$key]);
        $_SESSION[self::KEY] = $cart;
    }

    public static function has(string $key): bool
    {
        return isset(self::raw()[$key]);
    }

    /**
     * Work out which variant and how many from posted input (variant_id, or
     * opt1..opt3 option values; quantity), and check they can be bought.
     * Shared by "Add to cart" and plan start so both apply the same rules.
     * Returns ['variant' => ?array, 'qty' => int] or an error message.
     */
    public static function resolveItem(array $product, array $in): array|string
    {
        $variant = null;
        if (Product::variants((int) $product['id']) !== []) {
            $vid = (int) ($in['variant_id'] ?? 0);
            $variant = $vid > 0
                ? Product::findVariant((int) $product['id'], $vid)
                : Product::matchVariant($product, [1 => $in['opt1'] ?? '', 2 => $in['opt2'] ?? '', 3 => $in['opt3'] ?? '']);
            if ($variant === null) {
                $names = Product::optionNames($product);
                return 'Pick your ' . ($names ? strtolower(implode(' and ', $names)) : 'option') . ' first.';
            }
        }

        $qty = (int) ($in['quantity'] ?? 1);
        if ($qty < 1 || $qty > Product::MAX_QTY) {
            return 'You can put 1 to ' . Product::MAX_QTY . ' of an item on one plan.';
        }

        $stock = Product::stockFor($product, $variant);
        if ($stock !== null && $stock < $qty) {
            return $stock < 1
                ? 'Sorry, that one is sold out for now. Save it and check back.'
                : 'Only ' . $stock . ' left — lower the quantity and try again.';
        }
        return ['variant' => $variant, 'qty' => $qty];
    }

    /**
     * Lines with everything the cart page needs, newest first. A line whose
     * product, shop or variant is gone, or that is out of stock, carries a
     * `problem` message and can't be started.
     */
    public static function lines(): array
    {
        $out = [];
        foreach (array_reverse(self::raw(), true) as $key => $line) {
            $product = Product::find((int) $line['product_id']);
            $row = ['key' => (string) $key, 'qty' => (int) $line['qty'], 'product' => $product, 'variant' => null, 'problem' => ''];

            if (!$product || !$product['active'] || $product['merchant_status'] !== 'approved') {
                $row['problem'] = 'This item is no longer on sale.';
                $out[] = $row;
                continue;
            }
            $hasVariants = Product::variants((int) $product['id']) !== [];
            if ($line['variant_id'] !== null) {
                $row['variant'] = Product::findVariant((int) $product['id'], (int) $line['variant_id']);
                if ($row['variant'] === null) {
                    $row['problem'] = 'The option you picked was removed by the shop. Pick again.';
                }
            } elseif ($hasVariants) {
                $row['problem'] = 'Pick an option for this item first.';
            }

            $row['unit'] = Product::unitPrice($product, $row['variant']);
            $row['total'] = $row['unit'] * $row['qty'];
            $row['label'] = Product::variantLabel($product, $row['variant']);
            $row['stock'] = Product::stockFor($product, $row['variant']);
            if ($row['problem'] === '' && $row['stock'] !== null) {
                if ($row['stock'] < 1) {
                    $row['problem'] = 'Sold out for now.';
                } elseif ($row['stock'] < $row['qty']) {
                    $row['problem'] = 'Only ' . $row['stock'] . ' left. Lower the quantity to continue.';
                }
            }
            $out[] = $row;
        }
        return $out;
    }
}
