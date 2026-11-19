<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Game;

final class HomeController extends Controller
{
    private $games;

    public function __construct(Game $games)
    {
        $this->games = $games;
    }

    public function index(): void
    {
        $today = date('Y-m-d');
        $dateValue = $_GET['data'] ?? $today;
        $date = is_string($dateValue) ? trim($dateValue) : '';
        $dateError = '';
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
            $date = $today;
            $dateError = 'A data informada é inválida; exibindo os jogos de hoje.';
        }

        $user = $_SESSION['usuario'] ?? null;
        $gamesOfDay = $this->games->gamesForDate($date);
        $myGames = is_array($user)
            ? $this->games->gamesForPlayer((int) $user['id'])
            : [];
        $gameIds = array_merge(
            array_column($gamesOfDay, 'id'),
            array_column($myGames, 'id')
        );
        $lineups = is_array($user) ? $this->games->lineupsForGames($gameIds) : [];

        foreach ($gamesOfDay as &$game) {
            $game['lineup_a'] = $lineups[(int) $game['time_a_id']] ?? [];
            $game['lineup_b'] = $lineups[(int) $game['time_b_id']] ?? [];
        }
        unset($game);

        foreach ($myGames as &$game) {
            $game['lineup_a'] = $lineups[(int) $game['time_a_id']] ?? [];
            $game['lineup_b'] = $lineups[(int) $game['time_b_id']] ?? [];
        }
        unset($game);

        $this->render('home/index', [
            'user' => $user,
            'date' => $date,
            'dateError' => $dateError,
            'gamesOfDay' => $gamesOfDay,
            'myGames' => $myGames,
            'ranking' => $this->games->ranking(),
            'csrfToken' => $this->csrfToken(),
        ]);
    }
}
