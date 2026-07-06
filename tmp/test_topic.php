<?php
require 'c:\\xampp\\htdocs\\yenidosyalar\\includes\\init.php';
$settings = function_exists('seoSettings') ? seoSettings() : (function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : []);
$key = seoPublicPageResolveKey('/konu/ets2-tjs-hd-gps-modu-159-1', $settings, null);
var_dump($key);
