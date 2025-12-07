<?php

declare(strict_types=1);

use Fred\Infrastructure\Database\Migration\Migration;

return new class () implements Migration {
    public function getName(): string
    {
        return '20240617_profiles_per_community';
    }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS profiles_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    community_id INTEGER NOT NULL,
    bio TEXT DEFAULT '',
    location TEXT DEFAULT '',
    website TEXT DEFAULT '',
    signature_raw TEXT DEFAULT '',
    signature_parsed TEXT DEFAULT '',
    avatar_path TEXT DEFAULT '',
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    UNIQUE(user_id, community_id)
);
SQL);

        $profiles = $pdo->query('SELECT user_id, bio, location, website, signature_raw, signature_parsed, avatar_path, created_at, updated_at FROM profiles')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $communities = $pdo->query('SELECT id FROM communities')->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($communities === []) {
            $pdo->exec('DROP TABLE profiles');
            $pdo->exec('ALTER TABLE profiles_new RENAME TO profiles');

            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO profiles_new (user_id, community_id, bio, location, website, signature_raw, signature_parsed, avatar_path, created_at, updated_at)
             VALUES (:user_id, :community_id, :bio, :location, :website, :signature_raw, :signature_parsed, :avatar_path, :created_at, :updated_at)'
        );

        foreach ($profiles as $profile) {
            foreach ($communities as $community) {
                $insert->execute([
                    'user_id' => (int) $profile['user_id'],
                    'community_id' => (int) $community['id'],
                    'bio' => (string) $profile['bio'],
                    'location' => (string) $profile['location'],
                    'website' => (string) $profile['website'],
                    'signature_raw' => (string) $profile['signature_raw'],
                    'signature_parsed' => (string) $profile['signature_parsed'],
                    'avatar_path' => (string) $profile['avatar_path'],
                    'created_at' => (int) $profile['created_at'],
                    'updated_at' => (int) $profile['updated_at'],
                ]);
            }
        }

        $pdo->exec('DROP TABLE profiles');
        $pdo->exec('ALTER TABLE profiles_new RENAME TO profiles');
    }
};
