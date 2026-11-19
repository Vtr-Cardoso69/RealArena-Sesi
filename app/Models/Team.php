<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Team
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function playersForRoom(int $roomId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, nome, rm
             FROM usuario
             WHERE id_sala = :id_sala
               AND tipo IN ('aluno', 'representante')
             ORDER BY nome"
        );
        $statement->execute(['id_sala' => $roomId]);
        return $statement->fetchAll();
    }

    public function rosterForRoom(int $roomId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT m.id AS modalidade_id, m.nome AS modalidade_nome,
                    m.genero, m.max_jogadores, t.id AS time_id,
                    tj.id AS escalacao_id, u.nome AS jogador_nome, u.rm AS jogador_rm
             FROM modalidade m
             LEFT JOIN `time` t
               ON t.id_modalidade = m.id AND t.id_sala = :id_sala
             LEFT JOIN time_jogador tj ON tj.id_time = t.id
             LEFT JOIN usuario u ON u.id = tj.id_usuario
             ORDER BY m.nome, m.genero, u.nome'
        );
        $statement->execute(['id_sala' => $roomId]);
        return $statement->fetchAll();
    }

    public function addPlayer(int $roomId, int $modalityId, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $modalityStatement = $this->pdo->prepare(
                'SELECT max_jogadores FROM modalidade WHERE id = :id FOR UPDATE'
            );
            $modalityStatement->execute(['id' => $modalityId]);
            $maxPlayers = $modalityStatement->fetchColumn();
            if ($maxPlayers === false) {
                throw new \DomainException('Modalidade não encontrada.');
            }

            $playerStatement = $this->pdo->prepare(
                "SELECT id FROM usuario
                 WHERE id = :id
                   AND id_sala = :id_sala
                   AND tipo IN ('aluno', 'representante')
                 FOR UPDATE"
            );
            $playerStatement->execute([
                'id' => $userId,
                'id_sala' => $roomId,
            ]);
            if ($playerStatement->fetchColumn() === false) {
                throw new \DomainException('Selecione um aluno da sua própria sala.');
            }

            $teamInsert = $this->pdo->prepare(
                'INSERT IGNORE INTO `time` (id_sala, id_modalidade)
                 VALUES (:id_sala, :id_modalidade)'
            );
            $teamInsert->execute([
                'id_sala' => $roomId,
                'id_modalidade' => $modalityId,
            ]);

            $teamStatement = $this->pdo->prepare(
                'SELECT id FROM `time`
                 WHERE id_sala = :id_sala AND id_modalidade = :id_modalidade'
            );
            $teamStatement->execute([
                'id_sala' => $roomId,
                'id_modalidade' => $modalityId,
            ]);
            $teamId = $teamStatement->fetchColumn();
            if ($teamId === false) {
                throw new \RuntimeException('Não foi possível localizar o time da sala.');
            }

            $existingStatement = $this->pdo->prepare(
                'SELECT 1 FROM time_jogador
                 WHERE id_time = :id_time AND id_usuario = :id_usuario'
            );
            $existingStatement->execute([
                'id_time' => $teamId,
                'id_usuario' => $userId,
            ]);
            if ($existingStatement->fetchColumn() !== false) {
                throw new \DomainException('Este jogador já está escalado nesta modalidade.');
            }

            $countStatement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM time_jogador WHERE id_time = :id_time'
            );
            $countStatement->execute(['id_time' => $teamId]);
            if ((int) $countStatement->fetchColumn() >= (int) $maxPlayers) {
                throw new \DomainException(
                    'O limite de ' . (int) $maxPlayers . ' jogadores desta modalidade foi atingido.'
                );
            }

            $insertPlayer = $this->pdo->prepare(
                'INSERT INTO time_jogador (id_time, id_usuario)
                 VALUES (:id_time, :id_usuario)'
            );
            $insertPlayer->execute([
                'id_time' => $teamId,
                'id_usuario' => $userId,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function removePlayer(int $roomId, int $rosterId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE tj FROM time_jogador tj
             INNER JOIN `time` t ON t.id = tj.id_time
             WHERE tj.id = :id AND t.id_sala = :id_sala'
        );
        $statement->execute([
            'id' => $rosterId,
            'id_sala' => $roomId,
        ]);
        return $statement->rowCount() === 1;
    }
}
