<?php

declare(strict_types=1);

namespace Fred\Application\Search;

use PDO;

final readonly class SearchService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<int, array{
     *     thread_id:int,
     *     title:string,
     *     board_id:int,
     *     board_slug:string,
     *     board_name:string,
     *     author_name:string,
     *     created_at:int,
     *     snippet:string
     * }>
     */
    public function searchThreads(int $communityId, ?int $boardId, ?int $userId, string $query, int $limit = 10, int $offset = 0): array
    {
        $formatted = $this->formatQuery($query);

        if ($formatted === '') {
            return [];
        }

        $sql = <<<SQL
SELECT
    t.id AS thread_id,
    t.title,
    t.board_id,
    b.slug AS board_slug,
    b.name AS board_name,
    u.display_name AS author_name,
    t.created_at,
    snippet(threads_fts, 0, '', '', ' … ', 10) AS snippet,
    bm25(threads_fts) AS score
FROM threads_fts
JOIN threads t ON t.id = threads_fts.rowid
JOIN boards b ON b.id = t.board_id
JOIN users u ON u.id = t.author_id
WHERE threads_fts MATCH :query
  AND t.community_id = :community_id
SQL;

        $params = [
            ':query' => $formatted,
            ':community_id' => $communityId,
        ];

        if ($boardId !== null) {
            $sql .= ' AND t.board_id = :board_id';
            $params[':board_id'] = $boardId;
        }

        if ($userId !== null) {
            $sql .= ' AND t.author_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $sql .= ' ORDER BY score ASC, t.created_at DESC LIMIT :limit OFFSET :offset';

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $paramType = \is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($key, $value, $paramType);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array{
     *     post_id:int,
     *     thread_id:int,
     *     thread_title:string,
     *     board_id:int,
     *     board_slug:string,
     *     board_name:string,
     *     author_name:string,
     *     created_at:int,
     *     snippet:string
     * }>
     */
    public function searchPosts(int $communityId, ?int $boardId, ?int $userId, string $query, int $limit = 10, int $offset = 0): array
    {
        $formatted = $this->formatQuery($query);

        if ($formatted === '') {
            return [];
        }

        $sql = <<<SQL
SELECT
    p.id AS post_id,
    p.thread_id,
    t.title AS thread_title,
    t.board_id,
    b.slug AS board_slug,
    b.name AS board_name,
    u.display_name AS author_name,
    p.created_at,
    snippet(posts_fts, 0, '', '', ' … ', 12) AS snippet,
    bm25(posts_fts) AS score
FROM posts_fts
JOIN posts p ON p.id = posts_fts.rowid
JOIN threads t ON t.id = p.thread_id
JOIN boards b ON b.id = t.board_id
JOIN users u ON u.id = p.author_id
WHERE posts_fts MATCH :query
  AND p.community_id = :community_id
SQL;

        $params = [
            ':query' => $formatted,
            ':community_id' => $communityId,
        ];

        if ($boardId !== null) {
            $sql .= ' AND t.board_id = :board_id';
            $params[':board_id'] = $boardId;
        }

        if ($userId !== null) {
            $sql .= ' AND p.author_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $sql .= ' ORDER BY score ASC, p.created_at DESC LIMIT :limit OFFSET :offset';

        $statement = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $paramType = \is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($key, $value, $paramType);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function formatQuery(string $input): string
    {
        $terms = preg_split('/\s+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);

        if ($terms === false || $terms === []) {
            return '';
        }

        $formatted = [];

        foreach ($terms as $term) {
            $clean = preg_replace('/[^a-zA-Z0-9]+/', '', $term);

            if ($clean !== '' && $clean !== null) {
                $formatted[] = $clean . '*';
            }
        }

        return implode(' ', $formatted);
    }
}
