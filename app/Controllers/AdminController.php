<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Game;
use App\Models\Modality;
use App\Models\Room;
use App\Models\User;
use PDOException;

final class AdminController extends Controller
{
    private $rooms;
    private $modalities;
    private $games;
    private $users;

    public function __construct(
        Room $rooms,
        Modality $modalities,
        Game $games,
        User $users
    ) {
        $this->rooms = $rooms;
        $this->modalities = $modalities;
        $this->games = $games;
        $this->users = $users;
    }

    public function index(): void
    {
        if (!$this->requireRole(['admin'])) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handlePost();
        }

        $this->render('admin/index', [
            'rooms' => $this->rooms->allForManagement(),
            'modalities' => $this->modalities->all(),
            'games' => $this->games->gamesForManagement(),
            'flash' => $this->consumeFlash(),
            'csrfToken' => $this->csrfToken(),
        ]);
    }

    private function handlePost(): void
    {
        if (!$this->verifyCsrfToken()) {
            $this->setFlash('error', 'Sua sessão expirou. Atualize a página e tente novamente.');
            $this->redirect('admin');
        }

        $actionValue = $_POST['action'] ?? '';
        $action = is_string($actionValue) ? $actionValue : '';
        try {
            switch ($action) {
                case 'create_room':
                    $name = $this->stringField('nome');
                    $this->requireText($name, 100, 'Informe o nome da sala.');
                    if (strcasecmp($name, 'Diretoria') === 0) {
                        throw new \DomainException('O nome Diretoria é reservado.');
                    }
                    $this->rooms->create($name);
                    $this->setFlash('success', 'Sala cadastrada.');
                    break;

                case 'create_modality':
                    $name = $this->stringField('nome');
                    $gender = $this->stringField('genero');
                    $this->requireText($name, 100, 'Informe o nome da modalidade.');
                    if (!in_array($gender, ['Masculino', 'Feminino', 'Misto'], true)) {
                        throw new \DomainException('Selecione um gênero válido.');
                    }
                    $this->modalities->create(
                        $name,
                        $gender,
                        $this->integerField('max_jogadores', 1, 1000),
                        $this->integerField('pts_vitoria', 0, 100000),
                        $this->integerField('pts_empate', 0, 100000),
                        $this->integerField('pts_derrota', 0, 100000)
                    );
                    $this->setFlash('success', 'Modalidade cadastrada.');
                    break;

                case 'create_representative':
                    $name = $this->stringField('nome');
                    $rm = $this->stringField('rm');
                    $roomId = $this->integerField('id_sala', 1, PHP_INT_MAX);
                    $this->requireText($name, 150, 'Informe o nome do representante.');
                    $this->requireText($rm, 30, 'Informe um RM válido.');
                    if (!$this->rooms->contains($roomId)) {
                        throw new \DomainException('Selecione uma sala válida.');
                    }
                    $this->users->createRepresentative($name, $rm, $roomId);
                    $this->setFlash('success', 'Representante cadastrado. Ele poderá criar a senha no primeiro acesso.');
                    break;

                case 'schedule_game':
                    $modalityId = $this->integerField('id_modalidade', 1, PHP_INT_MAX);
                    $roomA = $this->integerField('id_sala_a', 1, PHP_INT_MAX);
                    $roomB = $this->integerField('id_sala_b', 1, PHP_INT_MAX);
                    if ($roomA === $roomB) {
                        throw new \DomainException('Selecione duas salas diferentes.');
                    }
                    $date = $this->dateField('dia');
                    $time = $this->stringField('horario');
                    if ($time !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
                        throw new \DomainException('Informe um horário válido.');
                    }
                    $time = $time === '' ? null : $time . ':00';
                    $court = $this->stringField('quadra');
                    $this->requireText($court, 100, 'Informe o local ou quadra.');
                    $this->games->schedule($modalityId, $roomA, $roomB, $date, $time, $court);
                    $this->setFlash('success', 'Jogo agendado. Os representantes já podem escalar os jogadores.');
                    break;

                case 'record_score':
                    $gameId = $this->integerField('id_jogo', 1, PHP_INT_MAX);
                    $scoreA = $this->integerField('gols_a', 0, 1000000);
                    $scoreB = $this->integerField('gols_b', 0, 1000000);
                    $this->games->recordScore($gameId, $scoreA, $scoreB);
                    $this->setFlash('success', 'Placar registrado. A pontuação e o ranking foram atualizados.');
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
            if ($action === 'create_modality') {
                $message = 'Já existe uma modalidade com esse nome e gênero.';
            } elseif ($action === 'create_room') {
                $message = 'Já existe uma sala com esse nome.';
            } else {
                $message = 'Este RM já está cadastrado no sistema.';
            }
            $this->setFlash('error', $message);
        }

        $this->redirect('admin');
    }

    private function integerField(string $field, int $minimum, int $maximum): int
    {
        $input = $_POST[$field] ?? null;
        $value = is_scalar($input) ? filter_var($input, FILTER_VALIDATE_INT) : false;
        if ($value === false || $value < $minimum || $value > $maximum) {
            throw new \DomainException('Um dos campos numéricos é inválido.');
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

    private function dateField(string $field): string
    {
        $value = $this->stringField($field);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \DomainException('Informe uma data válida.');
        }
        return $value;
    }

    private function requireText(string $value, int $maximum, string $message): void
    {
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($value === '' || $length > $maximum) {
            throw new \DomainException($message);
        }
    }
}
