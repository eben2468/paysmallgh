<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\HomeController;
use App\Controllers\MerchantController;
use App\Controllers\PlanController;
use App\Controllers\ReviewController;
use App\Controllers\ReviewModerationController;
use App\Controllers\SeoController;
use App\Controllers\ShopController;
use App\Controllers\WebhookController;
use App\Controllers\WishlistController;

// Public
$router->get('/', HomeController::class, 'index');
$router->get('/how-it-works', HomeController::class, 'howItWorks');
$router->get('/shop', ShopController::class, 'index');
$router->get('/product/{id}', ShopController::class, 'show');
$router->post('/product/{id}/review', ReviewController::class, 'store');
$router->post('/product/{id}/review/delete', ReviewController::class, 'destroy');
$router->post('/review/{id}/report', ReviewController::class, 'report');
$router->get('/search/suggest', ShopController::class, 'suggest');

// Search engines
$router->get('/robots.txt', SeoController::class, 'robots');
$router->get('/sitemap.xml', SeoController::class, 'sitemap');

// Cart (session-based; each line is started as its own plan)
$router->get('/cart', CartController::class, 'index');
$router->post('/cart/add', CartController::class, 'add');
$router->post('/cart/update', CartController::class, 'update');
$router->post('/cart/remove', CartController::class, 'remove');

// Saved items (wishlist)
$router->get('/wishlist', WishlistController::class, 'index');
$router->post('/wishlist/toggle', WishlistController::class, 'toggle');
$router->get('/wishlist/resume', WishlistController::class, 'resume');

// Customer auth
$router->get('/register', AuthController::class, 'registerForm');
$router->post('/register', AuthController::class, 'register');
$router->get('/login', AuthController::class, 'loginForm');
$router->post('/login', AuthController::class, 'login');
$router->get('/logout', AuthController::class, 'logout');
$router->get('/verify-phone', AuthController::class, 'verifyForm');
$router->post('/verify-phone', AuthController::class, 'verify');
$router->post('/verify-phone/resend', AuthController::class, 'verifyResend');
$router->get('/forgot-pin', AuthController::class, 'forgotForm');
$router->post('/forgot-pin', AuthController::class, 'forgot');
$router->get('/reset-pin', AuthController::class, 'resetForm');
$router->post('/reset-pin', AuthController::class, 'reset');

// Customer account
$router->get('/account', AccountController::class, 'index');
$router->post('/account/profile', AccountController::class, 'updateProfile');
$router->get('/account/phone', AccountController::class, 'phoneForm');
$router->post('/account/phone', AccountController::class, 'phoneSend');
$router->post('/account/phone/confirm', AccountController::class, 'phoneConfirm');
$router->post('/account/phone/cancel', AccountController::class, 'phoneCancel');
$router->get('/account/security', AccountController::class, 'securityForm');
$router->post('/account/pin', AccountController::class, 'changePin');
$router->get('/account/addresses', AccountController::class, 'addresses');
$router->get('/account/addresses/new', AccountController::class, 'addressForm');
$router->post('/account/addresses/new', AccountController::class, 'addressSave');
$router->get('/account/addresses/{id}/edit', AccountController::class, 'addressForm');
$router->post('/account/addresses/{id}/edit', AccountController::class, 'addressSave');
$router->post('/account/addresses/{id}/delete', AccountController::class, 'addressDelete');
$router->post('/account/addresses/{id}/default', AccountController::class, 'addressDefault');
$router->get('/account/payment-methods', AccountController::class, 'paymentMethods');
$router->post('/account/momo', AccountController::class, 'saveMomo');
$router->post('/account/cards/settings', AccountController::class, 'cardSettings');
$router->post('/account/cards/{id}/delete', AccountController::class, 'deleteCard');
$router->get('/account/history', AccountController::class, 'history');

// Plans (customer)
$router->post('/plan/start', PlanController::class, 'start');
$router->get('/plan/resume', PlanController::class, 'resume');
// Paystack return-after-checkout (works with or without a login).
$router->get('/payment/return', PlanController::class, 'paymentReturn');
$router->get('/plans', PlanController::class, 'index');
$router->get('/plan/{id}', PlanController::class, 'show');
$router->post('/plan/{id}/pay', PlanController::class, 'pay');
$router->post('/plan/{id}/check', PlanController::class, 'check');
$router->get('/plan/{id}/status', PlanController::class, 'status');
$router->post('/plan/{id}/cancel', PlanController::class, 'cancel');
$router->post('/plan/{id}/delete', PlanController::class, 'delete');
// Mock-mode stand-in for Paystack's hosted checkout (demo without real money).
$router->get('/checkout/mock', PlanController::class, 'mockCheckout');
$router->post('/checkout/mock/confirm', PlanController::class, 'mockConfirm');

// Merchant
$router->get('/merchant', MerchantController::class, 'landing');
$router->get('/merchant/register', MerchantController::class, 'registerForm');
$router->post('/merchant/register', MerchantController::class, 'register');
$router->get('/merchant/login', MerchantController::class, 'loginForm');
$router->post('/merchant/login', MerchantController::class, 'login');
$router->get('/merchant/logout', MerchantController::class, 'logout');
$router->get('/merchant/forgot-password', MerchantController::class, 'forgotForm');
$router->post('/merchant/forgot-password', MerchantController::class, 'forgot');
$router->get('/merchant/reset-password', MerchantController::class, 'resetForm');
$router->post('/merchant/reset-password', MerchantController::class, 'reset');
$router->post('/merchant/request-review', MerchantController::class, 'requestReview');
$router->get('/merchant/dashboard', MerchantController::class, 'dashboard');
$router->get('/merchant/settings', MerchantController::class, 'settingsForm');
$router->post('/merchant/settings', MerchantController::class, 'settingsSave');
$router->post('/merchant/plan/{id}/release', MerchantController::class, 'releasePlan');
$router->get('/merchant/products', MerchantController::class, 'products');
$router->get('/merchant/products/new', MerchantController::class, 'productForm');
$router->post('/merchant/products/new', MerchantController::class, 'productSave');
$router->get('/merchant/products/{id}/edit', MerchantController::class, 'productForm');
$router->post('/merchant/products/{id}/edit', MerchantController::class, 'productSave');
$router->post('/merchant/products/{id}/toggle', MerchantController::class, 'productToggle');
$router->post('/merchant/products/{id}/delete', MerchantController::class, 'productDelete');
$router->get('/merchant/payouts', MerchantController::class, 'payouts');

// Admin
$router->get('/admin/login', AdminController::class, 'loginForm');
$router->post('/admin/login', AdminController::class, 'login');
$router->post('/admin/logout', AdminController::class, 'logout');
$router->get('/admin', AdminController::class, 'dashboard');
$router->get('/admin/merchants', AdminController::class, 'merchants');
$router->get('/admin/sms', AdminController::class, 'sms');
$router->get('/admin/system', AdminController::class, 'system');
$router->post('/admin/merchant/{id}/approve', AdminController::class, 'approveMerchant');
$router->get('/admin/reviews', ReviewModerationController::class, 'index');
$router->post('/admin/review/{id}/approve', ReviewModerationController::class, 'approve');
$router->post('/admin/review/{id}/reject', ReviewModerationController::class, 'reject');
$router->post('/admin/review/{id}/delete', ReviewModerationController::class, 'delete');
$router->post('/admin/review/{id}/dismiss-reports', ReviewModerationController::class, 'dismissReports');
$router->post('/admin/review-photo/{id}/approve', ReviewModerationController::class, 'approvePhoto');
$router->post('/admin/review-photo/{id}/reject', ReviewModerationController::class, 'rejectPhoto');
$router->post('/admin/merchant/{id}/decline', AdminController::class, 'declineMerchant');
$router->post('/admin/merchant/{id}/suspend', AdminController::class, 'suspendMerchant');
$router->post('/admin/merchant/{id}/reactivate', AdminController::class, 'reactivateMerchant');
$router->post('/admin/merchant/{id}/verify', AdminController::class, 'verifyMerchant');
$router->post('/admin/merchant/{id}/unverify', AdminController::class, 'unverifyMerchant');
$router->get('/admin/merchant/{id}/id-card', AdminController::class, 'idCard');
$router->get('/admin/users', AdminController::class, 'users');
$router->get('/admin/merchant/{id}', AdminController::class, 'merchantDetail');
$router->get('/admin/user/{id}', AdminController::class, 'userDetail');
$router->post('/admin/test-sms', AdminController::class, 'testSms');
$router->post('/admin/poll-sms', AdminController::class, 'pollSms');
$router->get('/admin/plans', AdminController::class, 'plans');
$router->get('/admin/plan/{id}', AdminController::class, 'planDetail');
$router->get('/admin/ledger', AdminController::class, 'ledger');
$router->post('/admin/simulate-payment/{plan_id}', AdminController::class, 'simulatePayment');
$router->post('/admin/run-reminders', AdminController::class, 'runReminders');
$router->post('/admin/reconcile', AdminController::class, 'reconcile');
$router->post('/admin/plan/{id}/retry-payout', AdminController::class, 'retryPayout');
$router->post('/admin/plan/{id}/retry-refunds', AdminController::class, 'retryRefunds');

// Webhooks (no CSRF — external callers)
$router->post('/webhook/paystack', WebhookController::class, 'paystack');
$router->post('/webhook/ussd', WebhookController::class, 'ussd');
