<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Installment;
use App\Models\Merchant;
use App\Models\Product;

final class HomeController extends Controller
{
    public function index(): void
    {
        $all = Product::browse();

        // Budget bands with how many products fit each (empty bands are hidden).
        $budgets = [];
        foreach (Product::BUDGETS as $slug => $b) {
            $n = count(array_filter($all, static fn (array $p): bool => Product::inBudget((int) $p['cash_price_pesewas'], $slug)));
            if ($n > 0) {
                $budgets[$slug] = $b + ['count' => $n];
            }
        }

        $this->render('home/index', [
            'title' => 'PaySmallSmall — Pay small small, own it proper',
            'products' => array_slice($all, 0, 10),
            'popular' => Product::popular(10),
            'categoryCounts' => Product::categoryCounts(),
            'budgets' => $budgets,
            'shops' => Merchant::showcase(6),
            'recent' => Installment::recentPayments(6),
            'marquee' => array_slice($all, 0, 10),
        ]);
    }

    public function howItWorks(): void
    {
        $this->render('home/how-it-works', ['title' => 'How it works — PaySmallSmall']);
    }
}
