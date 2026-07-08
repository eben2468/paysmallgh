<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected View $view;

    public function __construct()
    {
        $this->view = new View();
    }

    protected function render(string $view, array $data = []): void
    {
        echo $this->view->render($view, $data);
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /** Require a logged-in customer, or bounce to login remembering where they were going. */
    protected function requireUser(): array
    {
        $user = Auth::user();
        if (!$user) {
            $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            flash('error', 'Log in first.');
            redirect('/login');
        }
        return $user;
    }

    protected function requireMerchant(): array
    {
        $merchant = Auth::merchant();
        if (!$merchant) {
            flash('error', 'Log in to your merchant account first.');
            redirect('/merchant/login');
        }
        return $merchant;
    }

    protected function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            redirect('/admin/login');
        }
    }
}
