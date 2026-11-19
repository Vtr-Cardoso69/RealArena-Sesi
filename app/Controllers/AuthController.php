<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

final class AuthController extends Controller
{
    private $users;

    public function __construct(User $users)
    {
        $this->users = $users;
    }

    public function showLogin(): void
    {
        if (isset($_SESSION['usuario'])) {
            $this->redirect('home');
        }

        $this->render('auth/login', [
            'stage' => 'rm',
            'rm' => '',
            'name' => '',
            'error' => '',
            'csrfToken' => $this->csrfToken(),
        ]);
    }

    public function login(): void
    {
        if (!$this->verifyCsrfToken()) {
            $this->renderLogin('rm', '', '', 'Sua sessão expirou. Atualize a página e tente novamente.');
            return;
        }

        $actionValue = $_POST['action'] ?? '';
        $rmValue = $_POST['rm'] ?? '';
        $action = is_string($actionValue) ? $actionValue : '';
        if (!is_string($rmValue)) {
            $this->renderLogin('rm', '', '', 'Informe um RM válido.');
            return;
        }
        $rm = trim($rmValue);
        if ($rm === '' || strlen($rm) > 30) {
            $this->renderLogin('rm', $rm, '', 'Informe um RM válido.');
            return;
        }

        $user = $this->users->findByRm($rm);
        if ($user === null) {
            $this->renderLogin('rm', $rm, '', 'RM não encontrado. Peça ao representante da sua sala para cadastrá-lo.');
            return;
        }

        if ($action === 'identify') {
            $stage = $this->users->hasPassword($user) ? 'password' : 'create_password';
            $this->renderLogin($stage, $rm, $user['nome'], '');
            return;
        }

        if ($action === 'create_password') {
            $passwordValue = $_POST['password'] ?? '';
            $confirmationValue = $_POST['password_confirmation'] ?? '';
            $password = is_string($passwordValue) ? $passwordValue : '';
            $confirmation = is_string($confirmationValue) ? $confirmationValue : '';

            if ($this->users->hasPassword($user)) {
                $this->renderLogin('password', $rm, $user['nome'], 'Este usuário já possui senha. Entre com ela.');
                return;
            }

            if (strlen($password) < 8) {
                $this->renderLogin('create_password', $rm, $user['nome'], 'A senha deve ter pelo menos 8 caracteres.');
                return;
            }

            if (!hash_equals($password, $confirmation)) {
                $this->renderLogin('create_password', $rm, $user['nome'], 'A confirmação da senha não confere.');
                return;
            }

            if (!$this->users->createPassword((int) $user['id'], $password)) {
                $this->renderLogin('password', $rm, $user['nome'], 'A senha já foi definida. Entre com a senha cadastrada.');
                return;
            }

            $this->establishSession($user);
            $this->redirect('home');
        }

        if ($action === 'authenticate') {
            $passwordValue = $_POST['password'] ?? '';
            $password = is_string($passwordValue) ? $passwordValue : '';
            if (!$this->users->verifyPassword($user, $password)) {
                $this->renderLogin('password', $rm, $user['nome'], 'Senha incorreta.');
                return;
            }

            $this->establishSession($user);
            $this->redirect('home');
        }

        $this->renderLogin('rm', $rm, '', 'Solicitação inválida. Tente novamente.');
    }

    public function logout(): void
    {
        if (!$this->verifyCsrfToken()) {
            http_response_code(400);
            echo 'Solicitação inválida. Atualize a página e tente novamente.';
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parameters['path'],
                $parameters['domain'],
                $parameters['secure'],
                $parameters['httponly']
            );
        }
        session_destroy();
        $this->redirect('home');
    }

    private function renderLogin(string $stage, string $rm, string $name, string $error): void
    {
        $this->render('auth/login', [
            'stage' => $stage,
            'rm' => $rm,
            'name' => $name,
            'error' => $error,
            'csrfToken' => $this->csrfToken(),
        ]);
    }

    private function establishSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id' => (int) $user['id'],
            'nome' => (string) $user['nome'],
            'rm' => (string) $user['rm'],
            'tipo' => (string) $user['tipo'],
            'id_sala' => (int) $user['id_sala'],
        ];
        unset($_SESSION['_csrf_token']);
    }
}
