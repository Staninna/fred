<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Domain\Moderation\Report;
use PDO;
use RuntimeException;

final readonly class ReportRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?Report
    {
        $statement = $this->pdo->prepare(
            'SELECT id, community_id, post_id, reporter_id, reason, status, created_at, updated_at
             FROM reports
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function create(int $communityId, int $postId, int $reporterId, string $reason, int $timestamp): Report
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO reports (community_id, post_id, reporter_id, reason, status, created_at, updated_at)
             VALUES (:community_id, :post_id, :reporter_id, :reason, :status, :created_at, :updated_at)'
        );

        $statement->execute([
            'community_id' => $communityId,
            'post_id' => $postId,
            'reporter_id' => $reporterId,
            'reason' => $reason,
            'status' => 'open',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        $report = $this->findById($id);

        if ($report === null) {
            throw new RuntimeException('Failed to create report.');
        }

        return $report;
    }

    public function updateStatus(int $id, string $status, int $timestamp): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE reports SET status = :status, updated_at = :updated_at WHERE id = :id'
        );

        $statement->execute([
            'status' => $status,
            'updated_at' => $timestamp,
            'id' => $id,
        ]);
    }

    /**
     * @return array<int, Report>
     */
    public function listByCommunity(int $communityId, ?string $status = null): array
    {
        $sql = 'SELECT id, community_id, post_id, reporter_id, reason, status, created_at, updated_at
                FROM reports
                WHERE community_id = :community_id';
        $params = ['community_id' => $communityId];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY status DESC, created_at DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows ?: []);
    }

    /**
     * @return array<int, array{
     *   report: Report,
     *   reporter_username: string,
     *   post_author_username: string,
     *   thread_id: int,
     *   thread_title: string
     * }>
     */
    public function listWithContext(int $communityId, ?string $status = null): array
    {
        $sql = <<<SQL
SELECT
    r.id, r.community_id, r.post_id, r.reporter_id, r.reason, r.status, r.created_at, r.updated_at,
    reporter.username AS reporter_username,
    post_author.username AS post_author_username,
    t.id AS thread_id,
    t.title AS thread_title
FROM reports r
JOIN users reporter ON reporter.id = r.reporter_id
JOIN posts p ON p.id = r.post_id
JOIN users post_author ON post_author.id = p.author_id
JOIN threads t ON t.id = p.thread_id
WHERE r.community_id = :community_id
SQL;

        $params = ['community_id' => $communityId];

        if ($status !== null) {
            $sql .= ' AND r.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY r.status DESC, r.created_at DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $row): array {
            return [
                'report' => $this->hydrate($row),
                'reporter_username' => (string) $row['reporter_username'],
                'post_author_username' => (string) $row['post_author_username'],
                'thread_id' => (int) $row['thread_id'],
                'thread_title' => (string) $row['thread_title'],
            ];
        }, $rows);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Report
    {
        return new Report(
            id: (int) $row['id'],
            communityId: (int) $row['community_id'],
            postId: (int) $row['post_id'],
            reporterId: (int) $row['reporter_id'],
            reason: (string) $row['reason'],
            status: (string) $row['status'],
            createdAt: (int) $row['created_at'],
            updatedAt: (int) $row['updated_at'],
        );
    }
}
