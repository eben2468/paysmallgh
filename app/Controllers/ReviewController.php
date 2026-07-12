<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Product;
use App\Models\Review;

final class ReviewController extends Controller
{
    /** A signed-in customer leaves (or updates) a review for a product. */
    public function store(string $id): void
    {
        $user = $this->requireUser();
        Csrf::check();

        $product = Product::find((int) $id);
        if (!$product) {
            flash('error', 'That product no longer exists.');
            redirect('/shop');
        }

        $rating = (int) ($_POST['rating'] ?? 0);
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($rating < 1 || $rating > 5) {
            flash('error', 'Pick a star rating from 1 to 5.');
            redirect('/product/' . $id . '#reviews');
        }
        if (mb_strlen($body) > 600) {
            $body = mb_substr($body, 0, 600);
        }

        Review::upsert((int) $product['id'], (int) $user['id'], $rating, $body);
        flash('success', 'Thanks for the review!');
        redirect('/product/' . $id . '#reviews');
    }
}
