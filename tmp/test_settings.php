<?php
require 'c:\\xampp\\htdocs\\yenidosyalar\\includes\\init.php';
$settings = function_exists('seoSettings') ? seoSettings() : getAdminSettings($pdo);
var_dump(json_decode($settings['seo_public_page_presets_json'] ?? '[]', true)['topic'] ?? null);
