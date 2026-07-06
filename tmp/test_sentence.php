<?php
$str = "ETS2 1.59 için TJS HD GPS modunu indirin. GPS İÇİN AKSESUAR SETLERİ Asılı Öğeler (Geniş ekran monitör kullanıy";
$maxLength = 160;

// Try to cut at the last sentence end
$truncated = mb_substr($str, 0, $maxLength - 3, 'UTF-8');
$lastSentenceEnd = preg_match('/.*[.!?]/us', $truncated, $matches);
if (!empty($matches[0])) {
    var_dump($matches[0]);
}
