<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/helpers.php';

// Modülleri yükle
require_once __DIR__ . '/../includes/src/Engine/Media/Support/helpers.php';
require_once __DIR__ . '/../includes/src/Engine/Users/Support/profile-helpers.php';
require_once __DIR__ . '/../includes/src/Engine/Users/Support/users-helpers.php';
require_once __DIR__ . '/../includes/src/Engine/Scraper/Support/helpers.php';
require_once __DIR__ . '/../includes/src/Engine/AdminQuality/Support/helpers.php';
require_once __DIR__ . '/../includes/src/Modules/Contact/Support/helpers.php';
require_once __DIR__ . '/../includes/src/Modules/Events/init.php';

// Admin panel auth + rol kontrolü
// CLI scripts must explicitly set ALLOW_CLI_ADMIN constant before including this file
if (PHP_SAPI === 'cli') {
    if (!defined('ALLOW_CLI_ADMIN') || ALLOW_CLI_ADMIN !== true) {
        fwrite(STDERR, "Error: CLI access to admin functions requires ALLOW_CLI_ADMIN constant\n");
        exit(1);
    }
} else {
    requireAdmin();
}
