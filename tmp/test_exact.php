<?php
$str = "GPS İÇİN AKSESUAR SETLERİ Asılı Öğeler (Geniş ekran monitör kullanıy";
$truncated = $str;
$truncated = preg_replace('/\s*[\(\[][^\)\]]*$/u', '', $truncated);
var_dump($truncated);
