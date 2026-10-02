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
use App\Services\PaystackService;

final class MerchantController extends Controller
{
    public function landing(): void
    {
        $this->render('merchant/landing', ['title' => 'Sell on PaySmallSmall']);
    }

    public function registerForm(): void
    {
        $this->render('merchant/register', [
            'title' => 'Register your shop — PaySmallSmall',
            'banks' => (new PaystackService())->ghanaBanks(),
        ]);
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
        $payout = $this->payoutFromPost($d['phone']);
        if (is_string($payout)) {
            flash('error', $payout);
            redirect('/merchant/register');
        }
        $d += $payout;

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
        if (Auth::merchantId()) {
            redirect('/merchant/dashboard');
        }
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
        Auth::logout('merchant');
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

        $this->renderPortal('merchant', 'merchant/dashboard', [
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
        $this->renderPortal('merchant', 'merchant/settings', [
            'title' => 'Shop settings — PaySmallSmall',
            'merchant' => $merchant,
            'banks' => (new PaystackService())->ghanaBanks(),
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
        ];

        if ($d['shop_name'] === '' || $d['owner_name'] === '') {
            flash('error', 'Shop name and owner name are required.');
            redirect('/merchant/settings');
        }
        $payout = $this->payoutFromPost($merchant['phone']);
        if (is_string($payout)) {
            flash('error', $payout);
            redirect('/merchant/settings');
        }
        $d += $payout;

        Merchant::updateDetails((int) $merchant['id'], $d);
        flash('success', 'Shop details saved.');
        redirect('/merchant/dashboard');
    }

    /**
     * Read + validate the payout account fields (partials/payout-fields).
     * Returns ['payout_channel', 'payout_number', 'payout_bank_code'] or an
     * error message for the customer.
     */
    private function payoutFromPost(string $businessPhone): array|string
    {
        $channel = ($_POST['payout_channel'] ?? '') === 'bank' ? 'bank' : 'momo';
        $number = preg_replace('/\D+/', '', (string) ($_POST['payout_number'] ?? ''));

        if ($channel === 'momo') {
            $phone = $number === '' ? $businessPhone : normalize_phone($number);
            if ($phone === null) {
                return 'That MoMo number doesn\'t look right. Use 024XXXXXXX or 233XXXXXXXXX.';
            }
            $network = strtoupper(trim((string) ($_POST['payout_network'] ?? '')));
            if (!isset(PaystackService::MOMO_NETWORKS[$network])) {
                $network = (string) momo_network($phone);
            }
            if ($network === '') {
                return 'Pick your MoMo network so we can pay you.';
            }
            return ['payout_channel' => 'momo', 'payout_number' => $phone, 'payout_bank_code' => $network];
        }

        $bank = strtoupper(trim((string) ($_POST['payout_bank'] ?? '')));
        $banks = (new PaystackService())->ghanaBanks();
        if ($bank === '' || !preg_match('/^[A-Z0-9]{1,20}$/', $bank) || ($banks && !isset($banks[$bank]))) {
            return 'Choose your bank so we can pay you.';
        }
        if (strlen($number) < 6) {
            return 'Enter your bank account number.';
        }
        return ['payout_channel' => 'bank', 'payout_number' => $number, 'payout_bank_code' => $bank];
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
        $this->renderPortal('merchant', 'merchant/products', [
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
        $this->renderPortal('merchant', 'merchant/product-form', [
            'title' => ($product ? 'Edit' : 'Add') . ' product — PaySmallSmall',
            'merchant' => $merchant,
            'product' => $product,
            'images' => $product ? Product::images((int) $id) : [],
            'variants' => $product ? Product::variants((int) $id) : [],
        ]);
    }

    public function productSave(?string $id = null): void
    {
        $merchant = $this->requireMerchant();
        Csrf::check();

        $d = [
            'merchant_id' => (int) $merchant['id'],
            'name' => trim((string) ($_POST['name'] ?? '')),
            'sku' => self::optionalText($_POST['sku'] ?? '', 64),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'specs' => self::optionalText($_POST['specs'] ?? '', 3000),
            'delivery_info' => self::optionalText($_POST['delivery_info'] ?? '', 1000),
            'return_policy' => self::optionalText($_POST['return_policy'] ?? '', 1000),
            'photo' => '',
            'cash_price_pesewas' => self::pesewas($_POST['price'] ?? '') ?? 0,
            'compare_at_pesewas' => self::pesewas($_POST['old_price'] ?? ''),
            'stock' => self::optionalCount($_POST['stock'] ?? ''),
            'category' => trim(strtolower((string) ($_POST['category'] ?? ''))),
            'plan_frequencies' => implode(',', array_values(array_intersect(
                array_keys(Product::FREQUENCIES),
                array_map('strval', (array) ($_POST['frequencies'] ?? []))
            ))),
            'option1_name' => mb_substr(trim((string) ($_POST['option1_name'] ?? '')), 0, 40),
            'option2_name' => mb_substr(trim((string) ($_POST['option2_name'] ?? '')), 0, 40),
            'option3_name' => mb_substr(trim((string) ($_POST['option3_name'] ?? '')), 0, 40),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        $back = $id ? "/merchant/products/{$id}/edit" : '/merchant/products/new';
        // On a validation error, send the merchant back with what they typed.
        $fail = static function (string $message) use ($back): never {
            $_SESSION['product_form_old'] = $_POST;
            flash('error', $message);
            redirect($back);
        };

        if ($d['name'] === '' || $d['cash_price_pesewas'] < 1000) {
            $fail('Give the product a name and a price of at least GHS 10.');
        }
        if ($d['compare_at_pesewas'] !== null && $d['compare_at_pesewas'] <= $d['cash_price_pesewas']) {
            $fail('The old price has to be higher than the price — or leave it empty if there\'s no discount.');
        }
        if (($_POST['stock'] ?? '') !== '' && $d['stock'] === null) {
            $fail('Stock must be a whole number (0 or more), or leave it empty if you don\'t count stock.');
        }
        // Category must come from the list — except a product already saved
        // under an older custom category may keep it.
        $current = $id !== null ? (Product::find((int) $id)['category'] ?? null) : null;
        if (!isset(Product::CATEGORIES[$d['category']]) && $d['category'] !== $current) {
            $fail('Pick a category from the list.');
        }
        if ($d['plan_frequencies'] === '') {
            $fail('Tick at least one way customers can pay small small: daily, weekly or monthly.');
        }

        $variants = $this->variantsFromPost($d);
        if (is_string($variants)) {
            $fail($variants);
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

        Product::saveVariants($pid, $variants);

        // Save any newly uploaded photos (multiple).
        $this->saveUploadedPhotos($pid, (int) $merchant['id']);

        // Keep the cover (products.photo) pointed at the first gallery image.
        Product::refreshCover($pid);

        redirect('/merchant/products');
    }

    /**
     * Variant rows from the product form (variants[n][id|opt1|opt2|opt3|sku|price|stock]).
     * Option names on the product decide which values each row needs. No
     * option names = no variants. Returns rows for Product::saveVariants() or
     * an error message.
     */
    private function variantsFromPost(array $d): array|string
    {
        $names = [];
        for ($i = 1; $i <= 3; $i++) {
            if ($d["option{$i}_name"] !== '') {
                $names[$i] = $d["option{$i}_name"];
            }
        }

        $rows = [];
        $seen = [];
        foreach (array_slice((array) ($_POST['variants'] ?? []), 0, 60) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $vals = [];
            for ($i = 1; $i <= 3; $i++) {
                $vals[$i] = mb_substr(trim((string) ($r["opt{$i}"] ?? '')), 0, 60);
            }
            if (implode('', $vals) === '') {
                continue; // blank row
            }
            if (!$names) {
                return 'You filled in option rows but no option names. Name them first (e.g. Colour, Storage), or clear the rows.';
            }
            foreach ($vals as $i => $v) {
                if (isset($names[$i]) && $v === '') {
                    return 'Every option row needs a ' . $names[$i] . '.';
                }
                if (!isset($names[$i])) {
                    $vals[$i] = ''; // value for an unnamed option — drop it
                }
            }
            $combo = mb_strtolower(implode('|', $vals));
            if (isset($seen[$combo])) {
                return 'Two option rows are the same (' . implode(' / ', array_filter($vals)) . '). Each row must be different.';
            }
            $seen[$combo] = true;

            $price = self::pesewas($r['price'] ?? '');
            if (($r['price'] ?? '') !== '' && ($price === null || $price < 1000)) {
                return 'Option prices must be at least GHS 10 — or leave the price empty to use the main price.';
            }
            $stock = self::optionalCount($r['stock'] ?? '');
            if (($r['stock'] ?? '') !== '' && $stock === null) {
                return 'Option stock must be a whole number (0 or more), or empty if you don\'t count it.';
            }
            $rows[] = [
                'id' => (int) ($r['id'] ?? 0),
                'option1' => $vals[1], 'option2' => $vals[2], 'option3' => $vals[3],
                'sku' => self::optionalText($r['sku'] ?? '', 64),
                'price_pesewas' => $price,
                'stock' => $stock,
            ];
        }

        if ($names && !$rows) {
            return 'You named options (' . implode(', ', $names) . ') but added no option rows. Add at least one, or clear the names.';
        }
        return $rows;
    }

    /** "1,250.50" (GHS) -> 125050 pesewas; empty/invalid -> null. */
    private static function pesewas(mixed $raw): ?int
    {
        $s = str_replace([',', ' '], '', trim((string) $raw));
        if ($s === '' || !is_numeric($s) || (float) $s < 0) {
            return null;
        }
        return (int) round((float) $s * 100);
    }

    /** Whole number 0..100000, or null when empty/invalid. */
    private static function optionalCount(mixed $raw): ?int
    {
        $s = trim((string) $raw);
        if ($s === '' || !ctype_digit($s) || (int) $s > 100000) {
            return null;
        }
        return (int) $s;
    }

    /** Trimmed text capped at $max characters, or null when empty. */
    private static function optionalText(mixed $raw, int $max): ?string
    {
        $s = trim((string) $raw);
        return $s === '' ? null : mb_substr($s, 0, $max);
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
        $this->renderPortal('merchant', 'merchant/payouts', [
            'title' => 'Payouts — PaySmallSmall',
            'merchant' => $merchant,
            'payouts' => Transaction::payoutsForMerchant((int) $merchant['id']),
        ]);
    }
}
