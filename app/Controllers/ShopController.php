<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;

final class ShopController extends Controller
{
    public function index(): void
    {
        $category = isset($_GET['category']) ? (string) $_GET['category'] : null;
        $this->render('shop/index', [
            'title' => 'Browse products — PaySmallSmall',
            'products' => Product::browse($category),
            'categories' => Product::categories(),
            'current' => $category,
        ]);
    }

    public function show(string $id): void
    {
        $product = Product::find((int) $id);
        if (!$product || !$product['active'] || $product['merchant_status'] !== 'approved') {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Product not found']);
            return;
        }

        // Plan picker options: sensible weekly counts for the price.
        $price = (int) $product['cash_price_pesewas'];
        $options = [];
        foreach ([4, 6, 8, 12, 16, 24] as $weeks) {
            $per = (int) ceil($price / $weeks);
            if ($per >= 2000) { // floor: GHS 20 per installment
                $options[] = ['weeks' => $weeks, 'per' => $per];
            }
        }
        if (!$options) {
            $options[] = ['weeks' => 4, 'per' => (int) ceil($price / 4)];
        }

        $this->render('shop/show', [
            'title' => $product['name'] . ' — PaySmallSmall',
            'product' => $product,
            'options' => $options,
        ]);
    }
}
