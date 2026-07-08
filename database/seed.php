<?php
declare(strict_types=1);

/**
 * Seed realistic demo data. Run from the project root:
 *   php database/seed.php
 *
 * Wipes and refills: users, merchants, products, plans, installments,
 * transactions, sms_log. Uses the real PlanService in mock mode so the
 * ledger and SMS log look exactly like production would.
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
require BASE_PATH . '/app/Core/helpers.php';

use App\Core\Config;
use App\Core\Database as DB;
use App\Models\Installment;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use App\Services\PlanService;

Config::load(BASE_PATH . '/.env');

if (Config::get('PAYMENTS_MODE') !== 'mock') {
    exit("Refusing to seed: set PAYMENTS_MODE=mock first so no real money moves.\n");
}

$pdo = DB::pdo();
echo "Clearing tables...\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['sms_log', 'transactions', 'installments', 'plans', 'products', 'merchants', 'users', 'ussd_sessions'] as $t) {
    $pdo->exec("TRUNCATE TABLE {$t}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "Creating merchants...\n";
$kofi = Merchant::create([
    'shop_name' => 'Kofi Mensah Phones & Accessories',
    'owner_name' => 'Kofi Mensah',
    'phone' => '233244111222',
    'location' => 'Kwame Nkrumah Circle, Accra',
    'password' => 'demo1234',
    'payout_channel' => 'momo',
    'payout_number' => '233244111222',
]);
$adjoa = Merchant::create([
    'shop_name' => 'Adjoa Serwaa Furniture Works',
    'owner_name' => 'Adjoa Serwaa',
    'phone' => '233209333444',
    'location' => 'Suame Magazine, Kumasi',
    'password' => 'demo1234',
    'payout_channel' => 'momo',
    'payout_number' => '233209333444',
]);
$efua = Merchant::create([
    'shop_name' => 'Efua Baidoo Fashion House',
    'owner_name' => 'Efua Baidoo',
    'phone' => '233551555666',
    'location' => 'Takoradi Market Circle',
    'password' => 'demo1234',
    'payout_channel' => 'momo',
    'payout_number' => '233551555666',
]);
Merchant::approve($kofi);
Merchant::approve($adjoa);
Merchant::approve($efua);

// A pending merchant so the admin approval queue has something in it.
Merchant::create([
    'shop_name' => 'Yaw Darko Electricals',
    'owner_name' => 'Yaw Darko',
    'phone' => '233277888999',
    'location' => 'Madina Market, Accra',
    'password' => 'demo1234',
    'payout_channel' => 'momo',
    'payout_number' => '233277888999',
]);

echo "Creating products...\n";
$P = fn(int $cedis) => $cedis * 100;
$products = [
    // Kofi — phone shop
    [$kofi, 'Samsung Galaxy A16', "Brand new, sealed. 128GB, dual SIM. One year warranty from the shop.", $P(1850), 'phones'],
    [$kofi, 'Tecno Spark 30C', "New in box. 256GB storage, big battery — lasts two days easy.", $P(1250), 'phones'],
    [$kofi, 'Infinix Hot 50i', "Sealed, 128GB. Good camera for the price. Free screen protector.", $P(1100), 'phones'],
    [$kofi, 'JBL Wave Buds (original)', "Original, not fake ones. Deep bass, 32-hour battery with the case.", $P(450), 'electronics'],
    // Adjoa — furniture
    [$adjoa, 'Double bed frame (mahogany)', "Solid mahogany, made in our Suame workshop. We deliver within Kumasi free.", $P(2400), 'furniture'],
    [$adjoa, '3-in-1 sofa set', "Strong fabric, wooden frame. Seats seven. Colour: brown or grey — your choice.", $P(3800), 'furniture'],
    [$adjoa, 'Dining table + 4 chairs', "Cedar wood, seats four. Perfect for a small flat.", $P(1900), 'furniture'],
    [$adjoa, 'Office desk', "1.2m desk with two drawers. Good for home office or shop counter.", $P(950), 'furniture'],
    // Efua — seamstress
    [$efua, 'Kaba and slit (custom sewn)', "Bring your own cloth or pick from ours. Measured and sewn to fit you proper.", $P(600), 'fashion'],
    [$efua, "Men's kaftan (2 sets)", "Two kaftans, your measurement, any colour. Ready in two weeks.", $P(800), 'fashion'],
    [$efua, 'School uniforms (bundle of 3)', "Three full uniforms sewn to your child's size. Ready before reopening.", $P(350), 'fashion'],
];
$productIds = [];
foreach ($products as [$mid, $name, $desc, $price, $cat]) {
    $productIds[$name] = Product::create([
        'merchant_id' => $mid,
        'name' => $name,
        'description' => $desc,
        'photo' => '',
        'cash_price_pesewas' => $price,
        'category' => $cat,
        'active' => 1,
    ]);
}

echo "Creating customers...\n";
$ama = User::create('Ama Owusu', '233241000001', '1234');
$kwame = User::create('Kwame Boateng', '233501000002', '1234');
$abena = User::create('Abena Asante', '233261000003', '1234');
$yaw = User::create('Yaw Ofori', '233541000004', '1234');

echo "Creating plans in various states...\n";
$svc = new PlanService();

/**
 * Start a plan and pay $payments installments through the real service,
 * then backdate the records so the demo timeline looks lived-in.
 */
function seedPlan(PlanService $svc, int $customerId, int $productId, int $count, string $freq, int $payments, int $weeksAgo): int
{
    $user = User::find($customerId);
    $product = Product::find($productId);
    $installment = (int) ceil($product['cash_price_pesewas'] / $count);
    [$planId] = $svc->startPlan($user, $product, $installment, $freq, $count);
    for ($i = 1; $i < $payments; $i++) {
        $svc->collectInstallment($planId);
    }
    // Backdate: plan creation and due dates shift into the past.
    $offset = "-{$weeksAgo} WEEK";
    DB::run("UPDATE plans SET created_at = DATE_ADD(NOW(), INTERVAL {$offset}) WHERE id = ?", [$planId]);
    DB::run("UPDATE installments SET due_date = DATE_ADD(due_date, INTERVAL {$offset}) WHERE plan_id = ?", [$planId]);
    return $planId;
}

// 1. Ama: phone plan, healthy, 5 of 12 weekly payments in.
seedPlan($svc, $ama, $productIds['Tecno Spark 30C'], 12, 'weekly', 5, 5);

// 2. Kwame: earbuds, nearly done — 8 of 9 paid.
seedPlan($svc, $kwame, $productIds['JBL Wave Buds (original)'], 9, 'weekly', 8, 8);

// 3. Abena: school uniforms, COMPLETED (pays out + SMS both sides).
seedPlan($svc, $abena, $productIds['School uniforms (bundle of 3)'], 5, 'weekly', 5, 6);

// 4. Yaw: dining table, fell behind — 3 of 10 paid, oldest unpaid overdue.
$late = seedPlan($svc, $yaw, $productIds['Dining table + 4 chairs'], 10, 'weekly', 3, 8);

// 5. Ama again: kaba and slit, just started — 1 of 6 paid.
seedPlan($svc, $ama, $productIds['Kaba and slit (custom sewn)'], 6, 'weekly', 1, 0);

// Run the reminder sweep so the late plan gets its grace/flag state + SMS.
$actions = $svc->runReminders();
foreach ($actions as $a) {
    echo "  {$a}\n";
}

echo "\nDone. Demo logins (all passwords/PINs shown):\n";
echo "  Customers (PIN 1234): Ama 0241000001, Kwame 0501000002, Abena 0261000003, Yaw 0541000004\n";
echo "  Merchants (password demo1234): Kofi 0244111222, Adjoa 0209333444, Efua 0551555666\n";
echo "  Pending merchant: Yaw Darko 0277888999 (approve in admin)\n";
echo "  Admin: phone " . Config::get('ADMIN_PHONE') . ", password " . Config::get('ADMIN_PASSWORD') . "\n";
