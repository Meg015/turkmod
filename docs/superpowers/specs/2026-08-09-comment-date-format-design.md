# Yorum Tarihi Formatı Tasarımı

## Amaç

Yorumlarda yedi günden eski tarihlerde görünen İngilizce ay formatı kaldırılacak ve sitenin ortak tarih+saat formatı kullanılacaktır. Yakın yorumlarda mevcut göreli ifadeler (`az önce`, `5 dk önce`, `2 saat önce`, `3 gün önce`) korunacaktır.

## Mevcut durum

`api/comments.php` içindeki `timeAgo()` fonksiyonu yedi günden eski yorumlarda doğrudan `date('d M Y')` kullanıyor. Bu PHP formatı, ay adını İngilizce kısaltmayla ürettiği için sitenin yönetim ayarlarıyla tutarlı değil. Projede ortak `formatAppDateTime()` yardımcısı, yönetim panelindeki `date_format` ve `time_format` ayarlarını birleştirerek tarih+saat üretmektedir.

## Tasarım

`timeAgo()` fonksiyonu isteğe bağlı bir `PDO` parametresi alacaktır. Yorum `formatComment()` içinde oluşturulurken mevcut global PDO bağlantısı bu parametreyle iletilecektir. Yedi günden eski tarihlerde:

- `formatAppDateTime()` mevcutsa ortak site formatı kullanılacak.
- Yardımcı kullanılamıyorsa güvenli fallback olarak `d.m.Y H:i` kullanılacak.

Yakın tarih eşikleri ve göreli metinler değişmeyecektir. Böylece API’nin gönderdiği `time_ago` değeri hem şablon hem JavaScript render’ında aynı, yerel ve ayarlanabilir formatta gösterilecektir.

## Veri akışı

```text
comments.created_at
        → formatComment()
        → timeAgo(created_at, PDO)
        ├─ 7 günden kısa: göreli Türkçe ifade
        └─ 7 gün ve üzeri: formatAppDateTime() → tarih + saat
```

İstemci tarafında tarih parse etme veya yeniden biçimlendirme yapılmayacaktır. Bu, sunucu saat dilimi ve yönetim ayarlarının tek kaynakta kalmasını sağlar.

## Doğrulama ölçütleri

1. Yedi günden eski yorumlar `09.08.2026 14:30` benzeri ortak tarih+saat formatında görünür.
2. Tarih formatı ve saat formatı yönetim ayarlarından okunur.
3. Yakın yorumlardaki göreli Türkçe ifadeler değişmeden kalır.
4. `formatAppDateTime()` kullanılamasa bile İngilizce ay adı üretilmez; `d.m.Y H:i` fallback’i kullanılır.
5. PHP sözdizimi kontrolü başarılı olur ve mevcut yorum API sözleşmesi korunur.

## Kapsam dışı

- Yorumların sıralama veya polling davranışını değiştirmek.
- JavaScript tarafında ikinci bir tarih formatlama sistemi eklemek.
- Yönetim panelindeki tarih/saat ayarlarının adını veya varsayılanlarını değiştirmek.
