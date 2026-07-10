<?php
namespace Controllers;
use Core\Controller;

class AuthController extends Controller {
    public function partner() {
        $this->view('partner/login');
    }

    public function guest() {
        $this->view('client/login');
    }
}
