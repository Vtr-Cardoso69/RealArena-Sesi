<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Room
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allForManagement(): array
    {
        return $this->pdo->query(
            "SELECT id, nome FROM sala
             WHERE nome <> 'Diretoria'
             ORDER BY nome"
        )->fetchAll();
    }

    public function create(string $name): void
    {
        $statement = $this->pdo->prepare('INSERT INTO sala (nome) VALUES (:nome)');
        $statement->execute(['nome' => $name]);
    }

    public function contains(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM sala WHERE id = :id AND nome <> 'Diretoria' LIMIT 1"
        );
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    public function nameById(int $id): string
    {
        $statement = $this->pdo->prepare('SELECT nome FROM sala WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $name = $statement->fetchColumn();
        return $name === false ? '' : (string) $name;
    }
}
