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
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
        $this->render('shop/index', [
            'title' => ($q ? "\"{$q}\" — search" : 'Browse products') . ' — PaySmallSmall',
            'products' => Product::browse($category, $q),
            'categories' => Product::categories(),
            'current' => $category,
            'q' => $q,
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

        $this->render('shop/show', [
            'title' => $product['name'] . ' — PaySmallSmall',
            'product' => $product,
            'plans' => $this->planOptions((int) $product['cash_price_pesewas']),
            'images' => Product::images((int) $id),
        ]);
    }

    /**
     * Build plan-picker options for each frequency. The customer picks how often
     * (daily/weekly/monthly) and over how many installments. We keep each
     * installment above a small floor so plans stay sensible, but always offer at
     * least one option — even cheap products get a plan.
     *
     * @return array<string, array{unit:string, noun:string, options: list<array{count:int, per:int, perLabel:string}>}>
     */
    private function planOptions(int $price): array
    {
        $floor = 100; // GHS 1.00 minimum per installment
        $defs = [
            'daily'   => ['unit' => 'day',   'noun' => 'days',   'counts' => [7, 14, 21, 30, 45, 60, 90]],
            'weekly'  => ['unit' => 'week',  'noun' => 'weeks',  'counts' => [4, 6, 8, 12, 16, 24, 36]],
            'monthly' => ['unit' => 'month', 'noun' => 'months', 'counts' => [2, 3, 4, 6, 9, 12]],
        ];

        $plans = [];
        foreach ($defs as $freq => $def) {
            $options = [];
            foreach ($def['counts'] as $count) {
                $per = (int) ceil($price / $count);
                if ($per >= $floor) {
                    $options[] = ['count' => $count, 'per' => $per, 'perLabel' => ghs($per)];
                }
            }
            if (!$options) { // price too small for any listed count — offer the fewest installments
                $count = $def['counts'][0];
                $per = (int) ceil($price / $count);
                $options[] = ['count' => $count, 'per' => $per, 'perLabel' => ghs($per)];
            }
            $plans[$freq] = ['unit' => $def['unit'], 'noun' => $def['noun'], 'options' => $options];
        }
        return $plans;
    }
}
