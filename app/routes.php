<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\MerchantController;
use App\Controllers\PlanController;
use App\Controllers\ShopController;
use App\Controllers\WebhookController;

// Public
$router->get('/', HomeController::class, 'index');
$router->get('/how-it-works', HomeController::class, 'howItWorks');
$router->get('/shop', ShopController::class, 'index');
$router->get('/product/{id}', ShopController::class, 'show');

// Customer auth
$router->get('/register', AuthController::class, 'registerForm');
$router->post('/register', AuthController::class, 'register');
$router->get('/login', AuthController::class, 'loginForm');
$router->post('/login', AuthController::class, 'login');
$router->get('/logout', AuthController::class, 'logout');

// Plans (customer)
$router->post('/plan/start', PlanController::class, 'start');
$router->get('/plans', PlanController::class, 'index');
$router->get('/plan/{id}', PlanController::class, 'show');
$router->post('/plan/{id}/pay', PlanController::class, 'pay');
$router->post('/plan/{id}/check', PlanController::class, 'check');
$router->post('/plan/{id}/cancel', PlanController::class, 'cancel');

// Merchant
$router->get('/merchant', MerchantController::class, 'landing');
$router->get('/merchant/register', MerchantController::class, 'registerForm');
$router->post('/merchant/register', MerchantController::class, 'register');
$router->get('/merchant/login', MerchantController::class, 'loginForm');
$router->post('/merchant/login', MerchantController::class, 'login');
$router->get('/merchant/logout', MerchantController::class, 'logout');
$router->get('/merchant/dashboard', MerchantController::class, 'dashboard');
$router->get('/merchant/products', MerchantController::class, 'products');
$router->get('/merchant/products/new', MerchantController::class, 'productForm');
$router->post('/merchant/products/new', MerchantController::class, 'productSave');
$router->get('/merchant/products/{id}/edit', MerchantController::class, 'productForm');
$router->post('/merchant/products/{id}/edit', MerchantController::class, 'productSave');
$router->post('/merchant/products/{id}/toggle', MerchantController::class, 'productToggle');
$router->get('/merchant/payouts', MerchantController::class, 'payouts');

// Admin
$router->get('/admin/login', AdminController::class, 'loginForm');
$router->post('/admin/login', AdminController::class, 'login');
$router->get('/admin', AdminController::class, 'dashboard');
$router->post('/admin/merchant/{id}/approve', AdminController::class, 'approveMerchant');
$router->get('/admin/plans', AdminController::class, 'plans');
$router->get('/admin/ledger', AdminController::class, 'ledger');
$router->post('/admin/simulate-payment/{plan_id}', AdminController::class, 'simulatePayment');
$router->post('/admin/run-reminders', AdminController::class, 'runReminders');
$router->post('/admin/reconcile', AdminController::class, 'reconcile');

// Webhooks (no CSRF — external callers)
$router->post('/webhook/moolre', WebhookController::class, 'moolre');
$router->post('/webhook/ussd', WebhookController::class, 'ussd');
