<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Modality;
use App\Models\Room;
use App\Models\Team;
use App\Models\User;
use PDOException;

final class RepresentativeController extends Controller
{
    private $users;
    private $modalities;
    private $teams;
    private $rooms;

    public function __construct(
        User $users,
        Modality $modalities,
        Team $teams,
        Room $rooms
    ) {
        $this->users = $users;
        $this->modalities = $modalities;
        $this->teams = $teams;
        $this->rooms = $rooms;
    }

    public function index(): void
    {
        if (!$this->requireRole(['representante'])) {
            return;
        }

        $roomId = (int) $_SESSION['usuario']['id_sala'];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handlePost($roomId);
        }

        $roster = [];
        foreach ($this->teams->rosterForRoom($roomId) as $row) {
            $modalityId = (int) $row['modalidade_id'];
            if (!isset($roster[$modalityId])) {
                $roster[$modalityId] = [
                    'nome' => $row['modalidade_nome'],
                    'genero' => $row['genero'],
                    'limite' => (int) $row['max_jogadores'],
                    'time_id' => $row['time_id'] === null ? null : (int) $row['time_id'],
                    'jogadores' => [],
                ];
            }
            if ($row['escalacao_id'] !== null) {
                $roster[$modalityId]['jogadores'][] = [
                    'id' => (int) $row['escalacao_id'],
                    'nome' => $row['jogador_nome'],
                    'rm' => $row['jogador_rm'],
                ];
            }
        }

        $this->render('representative/index', [
            'roomId' => $roomId,
            'roomName' => $this->rooms->nameById($roomId),
            'players' => $this->teams->playersForRoom($roomId),
            'modalities' => $this->modalities->all(),
            'roster' => $roster,
            'flash' => $this->consumeFlash(),
            'csrfToken' => $this->csrfToken(),
        ]);
    }

    private function handlePost(int $roomId): void
    {
        if (!$this->verifyCsrfToken()) {
            $this->setFlash('error', 'Sua sessão expirou. Atualize a página e tente novamente.');
            $this->redirect('representante');
        }

        $actionValue = $_POST['action'] ?? '';
        $action = is_string($actionValue) ? $actionValue : '';
        try {
            switch ($action) {
                case 'create_student':
                    $name = $this->stringField('nome');
                    $rm = $this->stringField('rm');
                    $this->requireText($name, 150, 'Informe o nome do aluno.');
                    $this->requireText($rm, 30, 'Informe um RM válido.');
                    $this->users->createStudent($name, $rm, $roomId);
                    $this->setFlash('success', 'Aluno cadastrado na sua sala. Ele poderá criar a senha no primeiro acesso.');
                    break;

                case 'add_player':
                    $modalityId = $this->integerField('id_modalidade');
                    $userId = $this->integerField('id_usuario');
                    $this->teams->addPlayer($roomId, $modalityId, $userId);
                    $this->setFlash('success', 'Jogador escalado na modalidade.');
                    break;

                case 'remove_player':
                    $rosterId = $this->integerField('id_escalacao');
                    if (!$this->teams->removePlayer($roomId, $rosterId)) {
                        throw new \DomainException('Escalação não encontrada nesta sala.');
                    }
                    $this->setFlash('success', 'Jogador removido da escalação.');
                    break;

                default:
                    throw new \DomainException('Ação inválida.');
            }
        } catch (\DomainException $exception) {
            $this->setFlash('error', $exception->getMessage());
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $this->setFlash('error', 'Este RM já está cadastrado no sistema.');
        }

        $this->redirect('representante');
    }

    private function integerField(string $field): int
    {
        $input = $_POST[$field] ?? null;
        $value = is_scalar($input) ? filter_var($input, FILTER_VALIDATE_INT) : false;
        if ($value === false || $value < 1) {
            throw new \DomainException('Selecione uma opção válida.');
        }
        return $value;
    }

    private function stringField(string $field): string
    {
        $value = $_POST[$field] ?? '';
        if (!is_string($value)) {
            throw new \DomainException('Um dos campos informados é inválido.');
        }
        return trim($value);
    }

    private function requireText(string $value, int $maximum, string $message): void
    {
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($value === '' || $length > $maximum) {
            throw new \DomainException($message);
        }
    }
}
