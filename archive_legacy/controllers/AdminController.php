<?php
namespace Controllers;
use Core\Controller;

class AdminController extends Controller {
    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $is_logged_in = !empty($_SESSION['admin_logged_in']);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
            $this->login();
        }
        
        if (isset($_GET['logout'])) {
            $this->logout();
        }

        if ($is_logged_in && empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        if (!$is_logged_in) {
            $this->view('admin/login');
        } else {
            $this->view('admin/dashboard');
        }
    }

    private function login() {
        $admin_user = $_ENV['ADMIN_USER'] ?? ($_SERVER['ADMIN_USER'] ?? 'anhthien');
        $admin_pass = $_ENV['ADMIN_PASS'] ?? ($_SERVER['ADMIN_PASS'] ?? 'Anhthien369@');
        
        $u = $_POST['username'] ?? '';
        $p = $_POST['password'] ?? '';
        
        if ($u === $admin_user && $p === $admin_pass) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: /admin/index.php');
            exit;
        } else {
            $this->view('admin/login', ['error' => 'Sai tên đăng nhập hoặc mật khẩu.']);
            exit;
        }
    }

    private function logout() {
        session_destroy();
        header('Location: /admin/index.php');
        exit;
    }
}
