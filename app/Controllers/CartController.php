<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Product;
use App\Services\Cart;

/**
 * The cart. Each line is started as its own plan (own escrow, own shop payout),
 * so the cart is where customers line items up and start them one by one.
 */
final class CartController extends Controller
{
    public function index(): void
    {
        $lines = Cart::lines();
        foreach ($lines as &$line) {
            $line['plans'] = ($line['problem'] === '' && $line['product'])
                ? Product::planOptions($line['total'], Product::allowedFrequencies($line['product']))
                : [];
        }
        unset($line);

        $this->render('cart/index', [
            'title' => 'Your cart — PaySmallSmall',
            'lines' => $lines,
        ]);
    }

    /** Add the item picked on a product page. Answers JSON for the in-page button, else redirects. */
    public function add(): void
    {
        Csrf::check();
        $product = Product::find((int) ($_POST['product_id'] ?? 0));
        if (!$product || !$product['active'] || $product['merchant_status'] !== 'approved') {
            $this->reply(false, 'That item isn\'t on sale any more.', '/shop');
        }
        $item = Cart::resolveItem($product, $_POST);
        if (is_string($item)) {
            $this->reply(false, $item, '/product/' . (int) $product['id']);
        }

        Cart::add((int) $product['id'], $item['variant'] !== null ? (int) $item['variant']['id'] : null, $item['qty']);
        $this->reply(true, 'Added to your cart.', '/product/' . (int) $product['id']);
    }

    public function update(): void
    {
        Csrf::check();
        Cart::setQty((string) ($_POST['key'] ?? ''), (int) ($_POST['quantity'] ?? 1));
        redirect('/cart');
    }

    public function remove(): void
    {
        Csrf::check();
        Cart::remove((string) ($_POST['key'] ?? ''));
        flash('success', 'Removed from your cart.');
        redirect('/cart');
    }

    /** JSON for fetch() callers (Accept: application/json), flash + redirect for plain forms. */
    private function reply(bool $ok, string $message, string $back): never
    {
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            $this->json(['ok' => $ok, 'message' => $message, 'count' => Cart::count(), 'cartUrl' => url('/cart')], $ok ? 200 : 422);
        }
        flash($ok ? 'success' : 'error', $message);
        redirect($back);
    }
}
