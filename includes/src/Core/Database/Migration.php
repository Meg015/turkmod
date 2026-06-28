<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;

interface Migration
{
    public function name(): string;

    public function up(PDO $pdo): void;

    public function down(PDO $pdo): void;
}
