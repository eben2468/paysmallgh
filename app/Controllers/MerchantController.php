<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Transaction;

final class MerchantController extends Controller
{
    public function landing(): void
    {
        $this->render('merchant/landing', ['title' => 'Sell on PaySmallSmall']);
    }

    public function registerForm(): void
    {
        $this->render('merchant/register', ['title' => 'Register your shop — PaySmallSmall']);
    }

    public function register(): void
    {
        Csrf::check();
        $d = [
            'shop_name' => trim((string) ($_POST['shop_name'] ?? '')),
            'owner_name' => trim((string) ($_POST['owner_name'] ?? '')),
            'phone' => normalize_phone((string) ($_POST['phone'] ?? '')),
            'location' => trim((string) ($_POST['location'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'payout_channel' => in_array($_POST['payout_channel'] ?? '', ['momo', 'bank'], true) ? $_POST['payout_channel'] : 'momo',
            'payout_number' => preg_replace('/\D+/', '', (string) ($_POST['payout_number'] ?? '')),
        ];

        if ($d['shop_name'] === '' || $d['owner_name'] === '') {
            flash('error', 'Shop name and owner name are required.');
            redirect('/merchant/register');
        }
        if ($d['phone'] === null) {
            flash('error', 'That phone number doesn\'t look right.');
            redirect('/merchant/register');
        }
        if (strlen($d['password']) < 8) {
            flash('error', 'Password needs at least 8 characters.');
            redirect('/merchant/register');
        }
        if (Merchant::findByPhone($d['phone'])) {
            flash('error', 'This number already has a shop. Log in instead.');
            redirect('/merchant/login');
        }
        if ($d['payout_number'] === '') {
            $d['payout_number'] = $d['phone'];
        }

        $id = Merchant::create($d);
        Auth::loginMerchant($id);
        flash('success', 'Shop registered! We\'ll review and approve it shortly — you can add products while you wait.');
        redirect('/merchant/dashboard');
    }

    public function loginForm(): void
    {
        $this->render('merchant/login', ['title' => 'Merchant log in — PaySmallSmall']);
    }

    public function login(): void
    {
        Csrf::check();
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $merchant = $phone ? Merchant::findByPhone($phone) : null;
        if (!$merchant || !password_verify($password, $merchant['password_hash'])) {
            flash('error', 'Phone or password no match.');
            redirect('/merchant/login');
        }

        Auth::loginMerchant((int) $merchant['id']);
        redirect('/merchant/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/merchant');
    }

    public function dashboard(): void
    {
        $merchant = $this->requireMerchant();
        $plans = Plan::forMerchant((int) $merchant['id']);

        $active = array_filter($plans, fn($p) => $p['status'] === 'active');
        $completed = array_filter($plans, fn($p) => $p['status'] === 'completed');
        $inEscrow = 0;
        foreach ($active as $p) {
            $inEscrow += (int) $p['installments_paid'] * (int) $p['installment_pesewas'];
        }

        $this->render('merchant/dashboard', [
            'title' => 'Dashboard — ' . $merchant['shop_name'],
            'merchant' => $merchant,
            'plans' => $plans,
            'stats' => [
                'active' => count($active),
                'completed' => count($completed),
                'in_escrow' => $inEscrow,
                'products' => count(Product::forMerchant((int) $merchant['id'])),
            ],
        ]);
    }

    public function products(): void
    {
        $merchant = $this->requireMerchant();
        $this->render('merchant/products', [
            'title' => 'My products — PaySmallSmall',
            'merchant' => $merchant,
            'products' => Product::forMerchant((int) $merchant['id']),
        ]);
    }

    public function productForm(?string $id = null): void
    {
        $merchant = $this->requireMerchant();
        $product = null;
        if ($id !== null) {
            $product = Product::find((int) $id);
            if (!$product || (int) $product['merchant_id'] !== (int) $merchant['id']) {
                redirect('/merchant/products');
            }
        }
        $this->render('merchant/product-form', [
            'title' => ($product ? 'Edit' : 'Add') . ' product — PaySmallSmall',
            'merchant' => $merchant,
            'product' => $product,
        ]);
    }

    public function productSave(?string $id = null): void
    {
        $merchant = $this->requireMerchant();
        Csrf::check();

        $priceCedis = (float) str_replace(',', '', (string) ($_POST['price'] ?? '0'));
        $d = [
            'merchant_id' => (int) $merchant['id'],
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'photo' => '',
            'cash_price_pesewas' => (int) round($priceCedis * 100),
            'category' => trim(strtolower((string) ($_POST['category'] ?? 'general'))) ?: 'general',
            'active' => isset($_POST['active']) ? 1 : 0,
        ];

        if ($d['name'] === '' || $d['cash_price_pesewas'] < 1000) {
            flash('error', 'Give the product a name and a price of at least GHS 10.');
            redirect($id ? "/merchant/products/{$id}/edit" : '/merchant/products/new');
        }

        // Photo upload (optional)
        if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $ext = match (mime_content_type($_FILES['photo']['tmp_name'])) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => null,
            };
            if ($ext !== null && $_FILES['photo']['size'] <= 4 * 1024 * 1024) {
                $name = 'p' . $merchant['id'] . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
                $dest = BASE_PATH . '/public/uploads/' . $name;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                    $d['photo'] = 'uploads/' . $name;
                }
            }
        }

        if ($id !== null) {
            $existing = Product::find((int) $id);
            if (!$existing || (int) $existing['merchant_id'] !== (int) $merchant['id']) {
                redirect('/merchant/products');
            }
            if ($d['photo'] === '') {
                $d['photo'] = $existing['photo'];
            }
            Product::update((int) $id, $d);
            flash('success', 'Product updated.');
        } else {
            Product::create($d);
            flash('success', 'Product added. Customers can see it once your shop is approved.');
        }
        redirect('/merchant/products');
    }

    public function productToggle(string $id): void
    {
        $merchant = $this->requireMerchant();
        Csrf::check();
        Product::toggle((int) $id, (int) $merchant['id']);
        redirect('/merchant/products');
    }

    public function payouts(): void
    {
        $merchant = $this->requireMerchant();
        $this->render('merchant/payouts', [
            'title' => 'Payouts — PaySmallSmall',
            'merchant' => $merchant,
            'payouts' => Transaction::payoutsForMerchant((int) $merchant['id']),
        ]);
    }
}
