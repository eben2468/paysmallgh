<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Product;
use App\Models\Wishlist;

/** Saved items. Needs an account so the list follows the customer to any phone. */
final class WishlistController extends Controller
{
    public function index(): void
    {
        $user = $this->requireUser();
        $this->render('wishlist/index', [
            'title' => 'Saved items — PaySmallSmall',
            'products' => Wishlist::products((int) $user['id']),
        ]);
    }

    /** Save or unsave a product. Answers JSON for the heart button, else redirects back. */
    public function toggle(): void
    {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $back = '/product/' . $productId;
        // Same-site paths only ("/shop?x=1", never "//host").
        if (isset($_POST['back']) && preg_match('#^/(?!/)[a-z0-9/_?=&%.+-]*$#i', (string) $_POST['back'])) {
            $back = (string) $_POST['back'];
        }

        // Guest: remember the item, log in, then save it for them.
        if (Auth::userId() === null) {
            $_SESSION['pending_wishlist'] = $productId;
            $_SESSION['after_login'] = '/wishlist/resume';
            flash('error', 'Log in or create a quick account to save items — we\'ll save this one for you.');
            redirect('/login');
        }

        Csrf::check();
        $product = Product::find($productId);
        if (!$product) {
            redirect('/shop');
        }
        $saved = Wishlist::toggle((int) Auth::userId(), $productId);

        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            $this->json(['ok' => true, 'saved' => $saved, 'count' => Wishlist::count((int) Auth::userId())]);
        }
        flash('success', $saved ? 'Saved. Find it under Saved items.' : 'Removed from your saved items.');
        redirect($back);
    }

    /** After logging in: save the item the guest tapped, then take them back to it. */
    public function resume(): void
    {
        $user = $this->requireUser();
        $productId = (int) ($_SESSION['pending_wishlist'] ?? 0);
        unset($_SESSION['pending_wishlist']);
        $product = $productId > 0 ? Product::find($productId) : null;
        if (!$product) {
            redirect('/wishlist');
        }
        Wishlist::add((int) $user['id'], $productId);
        flash('success', 'Saved. Find it under Saved items.');
        redirect('/product/' . $productId);
    }
}
