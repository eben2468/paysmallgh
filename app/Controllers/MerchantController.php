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
            'id_number' => strtoupper(trim((string) ($_POST['id_number'] ?? ''))),
            'business_reg' => trim((string) ($_POST['business_reg'] ?? '')),
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
        // Ghana Card: GHA-123456789-1 (allow with or without dashes/spaces).
        if (!preg_match('/^GHA[- ]?\d{9}[- ]?\d$/', $d['id_number'])) {
            flash('error', 'Enter a valid Ghana Card number, like GHA-123456789-1.');
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

        // Store the Ghana Card image outside the webroot (KYC — never public).
        $idPath = $this->storeIdCard($id);
        if ($idPath !== null) {
            Merchant::setIdCardPath($id, $idPath);
        }

        Auth::loginMerchant($id);
        flash('success', 'Shop registered! We\'ll review your details and approve you shortly — you can add products while you wait.');
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

    public function settingsForm(): void
    {
        $merchant = $this->requireMerchant();
        $this->render('merchant/settings', [
            'title' => 'Shop settings — PaySmallSmall',
            'merchant' => $merchant,
        ]);
    }

    public function settingsSave(): void
    {
        $merchant = $this->requireMerchant();
        Csrf::check();

        $d = [
            'shop_name' => trim((string) ($_POST['shop_name'] ?? '')),
            'owner_name' => trim((string) ($_POST['owner_name'] ?? '')),
            'location' => trim((string) ($_POST['location'] ?? '')),
            'payout_channel' => in_array($_POST['payout_channel'] ?? '', ['momo', 'bank'], true) ? $_POST['payout_channel'] : 'momo',
            'payout_number' => preg_replace('/\D+/', '', (string) ($_POST['payout_number'] ?? '')),
        ];

        if ($d['shop_name'] === '' || $d['owner_name'] === '') {
            flash('error', 'Shop name and owner name are required.');
            redirect('/merchant/settings');
        }
        if ($d['payout_number'] === '') {
            $d['payout_number'] = $merchant['phone'];
        }

        Merchant::updateDetails((int) $merchant['id'], $d);
        flash('success', 'Shop details saved.');
        redirect('/merchant/dashboard');
    }

    /** Merchant confirms they've handed over a paid-out item — closes the loop. */
    public function releasePlan(string $id): void
    {
        $merchant = $this->requireMerchant();
        Csrf::check();
        $plan = Plan::find((int) $id);
        if (!$plan || (int) $plan['merchant_id'] !== (int) $merchant['id']) {
            redirect('/merchant/dashboard');
        }
        if ($plan['status'] !== 'completed') {
            flash('error', 'You can only mark an item released once the plan is paid out.');
            redirect('/merchant/dashboard');
        }
        if (($plan['released_at'] ?? null) === null) {
            Plan::markReleased((int) $id);
            flash('success', 'Marked as released to ' . $plan['customer_name'] . '. That plan is fully closed.');
        }
        redirect('/merchant/dashboard');
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
            'images' => $product ? Product::images((int) $id) : [],
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

        if ($id !== null) {
            $existing = Product::find((int) $id);
            if (!$existing || (int) $existing['merchant_id'] !== (int) $merchant['id']) {
                redirect('/merchant/products');
            }
            $pid = (int) $id;
            Product::updateDetails($pid, $d);

            // Remove any images the merchant unchecked.
            foreach ((array) ($_POST['remove_images'] ?? []) as $imgId) {
                $path = Product::deleteImage((int) $imgId, $pid);
                if ($path !== null) {
                    @unlink(BASE_PATH . '/public/' . $path);
                }
            }
            flash('success', 'Product updated.');
        } else {
            $pid = Product::create($d);
            flash('success', 'Product added. Customers can see it once your shop is approved.');
        }

        // Save any newly uploaded photos (multiple).
        $this->saveUploadedPhotos($pid, (int) $merchant['id']);

        // Keep the cover (products.photo) pointed at the first gallery image.
        Product::refreshCover($pid);

        redirect('/merchant/products');
    }

    /** Move validated image uploads from photos[] into /public/uploads and record them. Caps at 8 per submit. */
    private function saveUploadedPhotos(int $productId, int $merchantId): void
    {
        $files = $_FILES['photos'] ?? null;
        if (!$files || !is_array($files['tmp_name'])) {
            return;
        }
        $sort = Product::maxImageSort($productId) + 1;
        $added = 0;
        $count = count($files['tmp_name']);
        for ($i = 0; $i < $count && $added < 8; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $path = $this->storeImage(
                (string) $files['tmp_name'][$i],
                (int) $files['size'][$i],
                $merchantId
            );
            if ($path !== null) {
                Product::addImage($productId, $path, $sort++);
                $added++;
            }
        }
    }

    /**
     * Store the merchant's Ghana Card image OUTSIDE the public webroot (KYC data
     * must never be directly reachable by URL). Returns the stored path relative
     * to /storage (e.g. "id_cards/xxx.jpg") or null if nothing valid was uploaded.
     */
    private function storeIdCard(int $merchantId): ?string
    {
        $f = $_FILES['id_card'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = (string) $f['tmp_name'];
        if ($tmp === '' || !is_uploaded_file($tmp) || (int) $f['size'] > 5 * 1024 * 1024) {
            return null;
        }
        $ext = match (mime_content_type($tmp)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($ext === null) {
            return null;
        }
        $dir = BASE_PATH . '/storage/id_cards';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        $name = 'id-' . $merchantId . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            return null;
        }
        return 'id_cards/' . $name;
    }

    /** Validate + move a single uploaded image. Returns the stored web path (uploads/xxx) or null. */
    private function storeImage(string $tmp, int $size, int $merchantId): ?string
    {
        if ($tmp === '' || !is_uploaded_file($tmp) || $size > 4 * 1024 * 1024) {
            return null;
        }
        $ext = match (mime_content_type($tmp)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($ext === null) {
            return null;
        }
        $name = 'p' . $merchantId . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($tmp, BASE_PATH . '/public/uploads/' . $name)) {
            return null;
        }
        return 'uploads/' . $name;
    }

    public function productToggle(string $id): void
    {
        $merchant = $this->requireMerchant();
        Csrf::check();
        Product::toggle((int) $id, (int) $merchant['id']);
        redirect('/merchant/products');
    }

    public function productDelete(string $id): void
    {
        $merchant = $this->requireMerchant();
        Csrf::check();
        $product = Product::find((int) $id);
        if (!$product || (int) $product['merchant_id'] !== (int) $merchant['id']) {
            redirect('/merchant/products');
        }
        // A product with customer plans must stay (records reference it) — hide it instead.
        if (Product::hasPlans((int) $id)) {
            flash('error', 'Customers have plans on this product, so it can\'t be deleted. Hide it instead.');
            redirect('/merchant/products');
        }

        $images = Product::images((int) $id);
        Product::delete((int) $id, (int) $merchant['id']);
        foreach ($images as $img) {
            if (!empty($img['path'])) {
                @unlink(BASE_PATH . '/public/' . $img['path']);
            }
        }
        flash('success', 'Product deleted.');
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
