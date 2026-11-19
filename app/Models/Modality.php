<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Modality
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        return $this->pdo->query(
            'SELECT id, nome, genero, max_jogadores, pts_vitoria, pts_empate, pts_derrota
             FROM modalidade
             ORDER BY nome, genero'
        )->fetchAll();
    }

    public function create(
        string $name,
        string $gender,
        int $maxPlayers,
        int $winPoints,
        int $drawPoints,
        int $lossPoints
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO modalidade
                (nome, genero, max_jogadores, pts_vitoria, pts_empate, pts_derrota)
             VALUES
                (:nome, :genero, :max_jogadores, :pts_vitoria, :pts_empate, :pts_derrota)'
        );
        $statement->execute([
            'nome' => $name,
            'genero' => $gender,
            'max_jogadores' => $maxPlayers,
            'pts_vitoria' => $winPoints,
            'pts_empate' => $drawPoints,
            'pts_derrota' => $lossPoints,
        ]);
    }
}
