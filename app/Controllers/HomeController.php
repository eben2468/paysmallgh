<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;

final class HomeController extends Controller
{
    public function index(): void
    {
        $products = array_slice(Product::browse(), 0, 8);
        $this->render('home/index', [
            'title' => 'PaySmallSmall — Pay small small, own it proper',
            'products' => $products,
        ]);
    }

    public function howItWorks(): void
    {
        $this->render('home/how-it-works', ['title' => 'How it works — PaySmallSmall']);
    }
}
