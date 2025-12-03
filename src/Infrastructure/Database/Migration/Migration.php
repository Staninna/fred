<?php

declare(strict_types=1);

namespace Fred\Infrastructure\Database\Migration;

use PDO;

interface Migration
{
    public function getName(): string;

    public function up(PDO $pdo): void;
}
