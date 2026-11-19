<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByRm(string $rm): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, nome, rm, senha, tipo, id_sala
             FROM usuario
             WHERE rm = :rm
             LIMIT 1'
        );
        $statement->execute(['rm' => $rm]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function hasPassword(array $user): bool
    {
        return isset($user['senha']) && $user['senha'] !== '';
    }

    public function verifyPassword(array $user, string $password): bool
    {
        return $this->hasPassword($user)
            && password_verify($password, (string) $user['senha']);
    }

    public function createPassword(int $userId, string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new \RuntimeException('Não foi possível proteger a senha.');
        }

        $statement = $this->pdo->prepare(
            "UPDATE usuario
             SET senha = :senha
             WHERE id = :id
               AND (senha IS NULL OR senha = '')"
        );
        $statement->execute([
            'senha' => $hash,
            'id' => $userId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function createStudent(string $name, string $rm, int $roomId): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO usuario (nome, rm, senha, tipo, id_sala)
             VALUES (:nome, :rm, NULL, 'aluno', :id_sala)"
        );
        $statement->execute([
            'nome' => $name,
            'rm' => $rm,
            'id_sala' => $roomId,
        ]);
    }

    public function createRepresentative(string $name, string $rm, int $roomId): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO usuario (nome, rm, senha, tipo, id_sala)
             VALUES (:nome, :rm, NULL, 'representante', :id_sala)"
        );
        $statement->execute([
            'nome' => $name,
            'rm' => $rm,
            'id_sala' => $roomId,
        ]);
    }
}
