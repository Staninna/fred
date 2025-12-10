<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use function count;

use Fred\Domain\Auth\User;
use PDO;
use RuntimeException;

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

    /**
     * @return array<int>
     */
    public function getModeratedCommunityIds(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT community_id FROM community_moderators WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        /** @var int[] $communityIds */
        $communityIds = $statement->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_map('intval', $communityIds);
    }

    /**
     * @param int[] $ids
     * @return array<int, User>
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // Normalize to sequential integers to match positional placeholders.
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            "SELECT u.id, u.username, u.display_name, u.password_hash, u.role_id, u.created_at,
                    r.slug AS role_slug, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id IN ($placeholders)"
        );

        $statement->execute($ids);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $users = [];

        foreach ($rows as $row) {
            $user = $this->hydrate($row);
            $users[$user->id] = $user;
        }

        return $users;
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
            throw new RuntimeException('Failed to reload created user.');
        }

        return $user;
    }

    public function updateRole(int $userId, int $roleId): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET role_id = :role_id WHERE id = :id');
        $statement->execute([
            'role_id' => $roleId,
            'id' => $userId,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function listUsernames(): array
    {
        $statement = $this->pdo->query('SELECT username FROM users ORDER BY username ASC');

        if ($statement === false) {
            return [];
        }

        return $statement->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * @return User[]
     */
    public function search(string $query, ?string $roleSlug = null, int $limit = 50, int $offset = 0): array
    {
        $sql = <<<SQL
SELECT u.id, u.username, u.display_name, u.password_hash, u.role_id, u.created_at,
       r.slug AS role_slug, r.name AS role_name
FROM users u
JOIN roles r ON r.id = u.role_id
WHERE 1=1
SQL;

        $params = [];

        if ($query !== '') {
            $sql .= ' AND (u.username LIKE :search OR u.display_name LIKE :search)';
            $params[':search'] = '%' . $query . '%';
        }

        if ($roleSlug !== null && $roleSlug !== '') {
            $sql .= ' AND r.slug = :role_slug';
            $params[':role_slug'] = $roleSlug;
        }

        $sql .= ' ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset';

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);

        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    /**
     * @param string[] $usernames
     * @return User[]
     */
    public function findByUsernames(array $usernames): array
    {
        $normalized = [];

        foreach ($usernames as $name) {
            $trimmed = strtolower(trim($name));

            if ($trimmed !== '') {
                $normalized[$trimmed] = $trimmed;
            }
        }

        if ($normalized === []) {
            return [];
        }

        $normalized = array_values($normalized);
        $placeholders = implode(',', array_fill(0, count($normalized), '?'));

        $statement = $this->pdo->prepare(
            "SELECT u.id, u.username, u.display_name, u.password_hash, u.role_id, u.created_at,
                    r.slug AS role_slug, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE lower(u.username) IN ($placeholders)"
        );

        $statement->execute($normalized);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrate'], $rows);
    }

    /** @param array<string, mixed> $row */
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
