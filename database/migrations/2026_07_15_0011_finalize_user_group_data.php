<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Engine\Users\Database\Migrations\Support\UserGroupDataInstaller;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_15_0011_finalize_user_group_data';
    }

    public function up(PDO $pdo): void
    {
        (new UserGroupDataInstaller())->install($pdo);
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Canonical user group assignments are not reverted automatically.');
    }
};
