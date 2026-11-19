<?php
declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\RepresentativeController;
use App\Models\Game;
use App\Models\Modality;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$cookiePath = $scriptDirectory === '/' || $scriptDirectory === '.'
    ? '/'
    : rtrim($scriptDirectory, '/') . '/';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, $cookiePath . '; samesite=Lax', '', $isHttps, true);
    }
    session_start();
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pageValue = $_GET['page'] ?? 'home';
$page = is_string($pageValue) ? $pageValue : 'home';

try {
    require dirname(__DIR__) . '/DB/conexao.php';

    $users = new User($pdo);
    $rooms = new Room($pdo);
    $modalities = new Modality($pdo);
    $teams = new Team($pdo);
    $games = new Game($pdo);

    $authController = new AuthController($users);
    $homeController = new HomeController($games);
    $adminController = new AdminController($rooms, $modalities, $games, $users);
    $representativeController = new RepresentativeController(
        $users,
        $modalities,
        $teams,
        $rooms
    );

    switch ($page) {
        case 'home':
            if ($method !== 'GET') {
                http_response_code(405);
                header('Allow: GET');
                echo 'Método não permitido.';
                break;
            }
            $homeController->index();
            break;

        case 'login':
            if ($method === 'GET') {
                $authController->showLogin();
            } elseif ($method === 'POST') {
                $authController->login();
            } else {
                http_response_code(405);
                header('Allow: GET, POST');
                echo 'Método não permitido.';
            }
            break;

        case 'logout':
            if ($method !== 'POST') {
                http_response_code(405);
                header('Allow: POST');
                echo 'Método não permitido.';
                break;
            }
            $authController->logout();
            break;

        case 'admin':
            if (!in_array($method, ['GET', 'POST'], true)) {
                http_response_code(405);
                header('Allow: GET, POST');
                echo 'Método não permitido.';
                break;
            }
            $adminController->index();
            break;

        case 'representante':
            if (!in_array($method, ['GET', 'POST'], true)) {
                http_response_code(405);
                header('Allow: GET, POST');
                echo 'Método não permitido.';
                break;
            }
            $representativeController->index();
            break;

        default:
            http_response_code(404);
            echo 'Página não encontrada.';
    }
} catch (Throwable $exception) {
    error_log((string) $exception);
    http_response_code(500);
    echo 'Não foi possível processar sua solicitação. Tente novamente mais tarde.';
}
