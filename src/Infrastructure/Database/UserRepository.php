<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Domain\Auth\User;
use PDO;

final readonly class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.username, u.display_name, u.password_hash, u.role_id, u.created_at,
                    r.slug AS role_slug, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1'
        );

        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByUsername(string $username): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.username, u.display_name, u.password_hash, u.role_id, u.created_at,
                    r.slug AS role_slug, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.username = :username
             LIMIT 1'
        );

        $statement->execute(['username' => $username]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function create(string $username, string $displayName, string $passwordHash, int $roleId, int $createdAt): User
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (username, display_name, password_hash, role_id, created_at)
             VALUES (:username, :display_name, :password_hash, :role_id, :created_at)'
        );

        $statement->execute([
            'username' => $username,
            'display_name' => $displayName,
            'password_hash' => $passwordHash,
            'role_id' => $roleId,
            'created_at' => $createdAt,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $user = $this->findById($id);
        if ($user === null) {
            throw new \RuntimeException('Failed to reload created user.');
        }

        return $user;
    }

    private function hydrate(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            username: (string) $row['username'],
            displayName: (string) $row['display_name'],
            passwordHash: (string) $row['password_hash'],
            roleId: (int) $row['role_id'],
            roleSlug: (string) $row['role_slug'],
            roleName: (string) $row['role_name'],
            createdAt: (int) $row['created_at'],
        );
    }
}
