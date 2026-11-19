<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Game
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function gamesForDate(string $date): array
    {
        $statement = $this->pdo->prepare($this->gameSelect() . '
             WHERE j.dia = :dia
             ORDER BY j.horario, j.id'
        );
        $statement->execute(['dia' => $date]);
        return $statement->fetchAll();
    }

    public function gamesForPlayer(int $userId): array
    {
        $statement = $this->pdo->prepare(
            $this->gameSelect() . '
             INNER JOIN time_jogador tj
               ON tj.id_time = j.id_timeA OR tj.id_time = j.id_timeB
             WHERE tj.id_usuario = :id_usuario
             ORDER BY j.dia, j.horario, j.id'
        );
        $statement->execute(['id_usuario' => $userId]);
        return $statement->fetchAll();
    }

    public function gamesForManagement(): array
    {
        return $this->pdo->query($this->gameSelect() . '
             ORDER BY j.dia DESC, j.horario, j.id DESC'
        )->fetchAll();
    }

    public function ranking(): array
    {
        return $this->pdo->query(
            "SELECT s.id, s.nome, COALESCE(SUM(resultado.pontos), 0) AS total_pontos
             FROM sala s
             LEFT JOIN (
                 SELECT tA.id_sala, p.pontos_sala_a AS pontos
                 FROM jogo j
                 INNER JOIN `time` tA ON tA.id = j.id_timeA
                 INNER JOIN placar p ON p.id_jogo = j.id
                 WHERE j.status = 'finalizado'
                 UNION ALL
                 SELECT tB.id_sala, p.pontos_sala_b AS pontos
                 FROM jogo j
                 INNER JOIN `time` tB ON tB.id = j.id_timeB
                 INNER JOIN placar p ON p.id_jogo = j.id
                 WHERE j.status = 'finalizado'
             ) resultado ON resultado.id_sala = s.id
             WHERE s.nome <> 'Diretoria'
             GROUP BY s.id, s.nome
             ORDER BY total_pontos DESC, s.nome"
        )->fetchAll();
    }

    public function lineupsForGames(array $gameIds): array
    {
        $gameIds = array_values(array_unique(array_filter(
            array_map('intval', $gameIds),
            function (int $id): bool {
                return $id > 0;
            }
        )));
        if ($gameIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
        $statement = $this->pdo->prepare(
            'SELECT t.id AS time_id, u.nome, u.rm
             FROM jogo j
             INNER JOIN `time` t
               ON t.id = j.id_timeA OR t.id = j.id_timeB
             INNER JOIN time_jogador tj ON tj.id_time = t.id
             INNER JOIN usuario u ON u.id = tj.id_usuario
             WHERE j.id IN (' . $placeholders . ')
             ORDER BY u.nome'
        );
        $statement->execute($gameIds);

        $lineups = [];
        foreach ($statement->fetchAll() as $player) {
            $lineups[(int) $player['time_id']][] = $player;
        }
        return $lineups;
    }

    public function schedule(
        int $modalityId,
        int $roomA,
        int $roomB,
        string $date,
        ?string $time,
        string $court
    ): void {
        $this->pdo->beginTransaction();
        try {
            $modalityStatement = $this->pdo->prepare(
                'SELECT id FROM modalidade WHERE id = :id FOR UPDATE'
            );
            $modalityStatement->execute(['id' => $modalityId]);
            if ($modalityStatement->fetchColumn() === false) {
                throw new \DomainException('Modalidade não encontrada.');
            }

            $roomsStatement = $this->pdo->prepare(
                "SELECT id FROM sala
                 WHERE id IN (:sala_a, :sala_b) AND nome <> 'Diretoria'
                 FOR UPDATE"
            );
            $roomsStatement->execute([
                'sala_a' => $roomA,
                'sala_b' => $roomB,
            ]);
            if (count($roomsStatement->fetchAll()) !== 2) {
                throw new \DomainException('Selecione duas salas válidas.');
            }

            $teamInsert = $this->pdo->prepare(
                'INSERT IGNORE INTO `time` (id_sala, id_modalidade)
                 VALUES (:id_sala, :id_modalidade)'
            );
            $teamSelect = $this->pdo->prepare(
                'SELECT id FROM `time`
                 WHERE id_sala = :id_sala AND id_modalidade = :id_modalidade'
            );
            $teamIds = [];
            foreach ([$roomA, $roomB] as $roomId) {
                $teamInsert->execute([
                    'id_sala' => $roomId,
                    'id_modalidade' => $modalityId,
                ]);
                $teamSelect->execute([
                    'id_sala' => $roomId,
                    'id_modalidade' => $modalityId,
                ]);
                $teamIds[] = (int) $teamSelect->fetchColumn();
            }

            $gameInsert = $this->pdo->prepare(
                'INSERT INTO jogo
                    (id_modalidade, id_timeA, id_timeB, dia, horario, quadra)
                 VALUES
                    (:id_modalidade, :time_a, :time_b, :dia, :horario, :quadra)'
            );
            $gameInsert->execute([
                'id_modalidade' => $modalityId,
                'time_a' => $teamIds[0],
                'time_b' => $teamIds[1],
                'dia' => $date,
                'horario' => $time,
                'quadra' => $court,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function recordScore(int $gameId, int $scoreA, int $scoreB): void
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'SELECT j.id_timeA, j.id_timeB,
                        m.pts_vitoria, m.pts_empate, m.pts_derrota
                 FROM jogo j
                 INNER JOIN modalidade m ON m.id = j.id_modalidade
                 WHERE j.id = :id
                 FOR UPDATE'
            );
            $statement->execute(['id' => $gameId]);
            $game = $statement->fetch();
            if ($game === false) {
                throw new \DomainException('Jogo não encontrado.');
            }

            if ($scoreA > $scoreB) {
                $winner = (int) $game['id_timeA'];
                $pointsA = (int) $game['pts_vitoria'];
                $pointsB = (int) $game['pts_derrota'];
            } elseif ($scoreB > $scoreA) {
                $winner = (int) $game['id_timeB'];
                $pointsA = (int) $game['pts_derrota'];
                $pointsB = (int) $game['pts_vitoria'];
            } else {
                $winner = null;
                $pointsA = (int) $game['pts_empate'];
                $pointsB = (int) $game['pts_empate'];
            }

            $scoreStatement = $this->pdo->prepare(
                'INSERT INTO placar
                    (id_jogo, gols_pts_a, gols_pts_b, pontos_sala_a, pontos_sala_b)
                 VALUES
                    (:id_jogo, :gols_a, :gols_b, :pontos_a, :pontos_b)
                 ON DUPLICATE KEY UPDATE
                    gols_pts_a = VALUES(gols_pts_a),
                    gols_pts_b = VALUES(gols_pts_b),
                    pontos_sala_a = VALUES(pontos_sala_a),
                    pontos_sala_b = VALUES(pontos_sala_b)'
            );
            $scoreStatement->execute([
                'id_jogo' => $gameId,
                'gols_a' => $scoreA,
                'gols_b' => $scoreB,
                'pontos_a' => $pointsA,
                'pontos_b' => $pointsB,
            ]);

            $gameStatement = $this->pdo->prepare(
                "UPDATE jogo
                 SET status = 'finalizado', id_time_vencedor = :vencedor
                 WHERE id = :id"
            );
            $gameStatement->execute([
                'vencedor' => $winner,
                'id' => $gameId,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function gameSelect(): string
    {
        return 'SELECT j.id, j.id_modalidade, j.id_timeA AS time_a_id,
                       j.id_timeB AS time_b_id, j.dia, j.horario, j.quadra, j.status,
                       m.nome AS modalidade, m.genero,
                       sA.nome AS sala_a, sB.nome AS sala_b,
                       p.gols_pts_a, p.gols_pts_b
                FROM jogo j
                INNER JOIN modalidade m ON m.id = j.id_modalidade
                INNER JOIN `time` tA ON tA.id = j.id_timeA
                INNER JOIN sala sA ON sA.id = tA.id_sala
                INNER JOIN `time` tB ON tB.id = j.id_timeB
                INNER JOIN sala sB ON sB.id = tB.id_sala
                LEFT JOIN placar p ON p.id_jogo = j.id';
    }
}
