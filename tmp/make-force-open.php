<?php
$preview = file_get_contents('tmp/popup-announcement-preview.html');
// Force popup open immediately by injecting CSS override
$override = '<style>
.popup-announcement-overlay { opacity: 1 !important; visibility: visible !important; transition: none !important; }
.popup-announcement-card { opacity: 1 !important; transform: none !important; animation: none !important; }
</style>';
$preview = str_replace('</head>', $override . '</head>', $preview);
file_put_contents('tmp/popup-force-open.html', $preview);
echo 'OK: ' . filesize('tmp/popup-force-open.html') . ' bytes' . PHP_EOL;
