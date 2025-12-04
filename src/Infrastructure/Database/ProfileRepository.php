<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database;

use Fred\Domain\Auth\Profile;
use PDO;

final class ProfileRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByUserId(int $userId): ?Profile
    {
        $statement = $this->pdo->prepare(
            'SELECT id, user_id, bio, location, website, signature_raw, signature_parsed, avatar_path, created_at, updated_at
             FROM profiles
             WHERE user_id = :user_id
             LIMIT 1'
        );

        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function create(
        int $userId,
        string $bio,
        string $location,
        string $website,
        string $signatureRaw,
        string $signatureParsed,
        string $avatarPath,
        int $timestamp,
    ): Profile {
        $statement = $this->pdo->prepare(
            'INSERT INTO profiles (user_id, bio, location, website, signature_raw, signature_parsed, avatar_path, created_at, updated_at)
             VALUES (:user_id, :bio, :location, :website, :signature_raw, :signature_parsed, :avatar_path, :created_at, :updated_at)'
        );

        $statement->execute([
            'user_id' => $userId,
            'bio' => $bio,
            'location' => $location,
            'website' => $website,
            'signature_raw' => $signatureRaw,
            'signature_parsed' => $signatureParsed,
            'avatar_path' => $avatarPath,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $profile = $this->findByUserId($userId);
        if ($profile === null) {
            throw new \RuntimeException('Failed to create profile.');
        }

        return $profile;
    }

    public function updateSignature(int $userId, string $raw, string $parsed, int $timestamp): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE profiles
             SET signature_raw = :signature_raw,
                 signature_parsed = :signature_parsed,
                 updated_at = :updated_at
             WHERE user_id = :user_id'
        );

        $statement->execute([
            'signature_raw' => $raw,
            'signature_parsed' => $parsed,
            'updated_at' => $timestamp,
            'user_id' => $userId,
        ]);
    }

    public function updateProfile(
        int $userId,
        string $bio,
        string $location,
        string $website,
        string $avatarPath,
        int $timestamp,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE profiles
             SET bio = :bio,
                 location = :location,
                 website = :website,
                 avatar_path = :avatar_path,
                 updated_at = :updated_at
             WHERE user_id = :user_id'
        );

        $statement->execute([
            'bio' => $bio,
            'location' => $location,
            'website' => $website,
            'avatar_path' => $avatarPath,
            'updated_at' => $timestamp,
            'user_id' => $userId,
        ]);
    }

    private function hydrate(array $row): Profile
    {
        return new Profile(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            bio: (string) $row['bio'],
            location: (string) $row['location'],
            website: (string) $row['website'],
            signatureRaw: (string) $row['signature_raw'],
            signatureParsed: (string) $row['signature_parsed'],
            avatarPath: (string) $row['avatar_path'],
            createdAt: (int) $row['created_at'],
            updatedAt: (int) $row['updated_at'],
        );
    }
}
