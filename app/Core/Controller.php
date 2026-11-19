<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = []): void
    {
        $viewFile = dirname(__DIR__) . '/Views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View não encontrada.');
        }

        extract($data, EXTR_SKIP);
        require $viewFile;
    }

    protected function redirect(string $page): void
    {
        header('Location: index.php?page=' . rawurlencode($page), true, 303);
        exit;
    }

    protected function csrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    protected function verifyCsrfToken(): bool
    {
        $submittedToken = $_POST['_csrf_token'] ?? '';
        $sessionToken = $_SESSION['_csrf_token'] ?? '';

        return is_string($submittedToken)
            && is_string($sessionToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $submittedToken);
    }

    protected function setFlash(string $type, string $message): void
    {
        $_SESSION['_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    protected function consumeFlash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        return is_array($flash) ? $flash : null;
    }

    protected function requireRole(array $allowedRoles): bool
    {
        $user = $_SESSION['usuario'] ?? null;
        if (!is_array($user)) {
            $this->redirect('login');
            return false;
        }

        if (!in_array($user['tipo'] ?? '', $allowedRoles, true)) {
            http_response_code(403);
            $this->render('errors/403');
            return false;
        }

        return true;
    }
}
