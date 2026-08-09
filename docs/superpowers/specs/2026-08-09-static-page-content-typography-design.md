# Sabit Sayfa İçerik Tipografisi Tasarımı

## Amaç

Sabit sayfaların public görünümünde güncelleme tarihini doğal Türkçe biçimde göstermek, uzun metinlerin okunabilirliğini artırmak ve zengin editörden gelen içerik elemanlarını tutarlı bir görsel sistem altında toplamak.

## Kapsam

Değişiklikler yalnızca sabit sayfa public şablonunu ve sabit sayfaya özel CSS dosyalarını kapsar. Yönetim paneli, veri tabanı, editör, konu detayları ve diğer public sayfalar değişmez.

## Tarih görünümü

- `updated_at` değeri sunucu tarafında doğrulanır ve Türkçe ay adıyla biçimlendirilir.
- Görünen biçim `9 Ağustos 2026` olur; saat gösterilmez.
- Tarih geçersiz veya boşsa güncelleme satırı hiç oluşturulmaz.
- Makine tarafından okunabilir özgün değer semantik `<time datetime="...">` elemanında korunur.
- Biçimlendirme sabit sayfa sınıfı içinde izole edilir; uygulamanın genel tarih ayarları değiştirilmez.

## Okuma genişliği

- Başlık ve içerik kartları breadcrumb ile aynı tam genişlikte kalır.
- Paragraf, içerik başlığı, liste, alıntı ve kod bloklarının okunabilir genişliği en fazla `900px` olur.
- Metin akışı kartın sol iç hizasından başlar; gereksiz ortalama uygulanmaz.
- Görseller, iframe içerikleri ve tablolar kartın kullanılabilir tam genişliğine çıkabilir.
- Görsel veya iframe içeren paragraf sarmalayıcıları metin genişliği sınırına takılmaz.
- Mobil ekranlarda bütün içerik doğal olarak yüzde yüz kullanılabilir genişliğe iner ve yatay taşma oluşturmaz.

## Zengin içerik sistemi

- `H1–H6` başlıkları tutarlı boyut, ağırlık, satır yüksekliği ve dikey aralıklarla gösterilir. İçerik içindeki `H1`, sayfa başlığıyla yarışmayacak ölçüde tutulur.
- Paragraflar rahat bir satır yüksekliği ve düzenli alt boşluk kullanır.
- Bağlantılar birincil renkle, belirgin alt çizgi ve klavye odağıyla gösterilir.
- Sıralı ve sırasız listelerde girinti, madde işareti rengi ve öğe aralıkları standardize edilir.
- Alıntı blokları mevcut birincil renkli sol kenarı ve açık/koyu tema kontrastını korur.
- Tablolar tam genişlik, belirgin başlık satırı, hücre kenarlıkları ve dar ekranlarda yatay kaydırma kullanır.
- `pre` ve `code` öğeleri okunabilir monospace görünüm, taşma kontrolü ve tema uyumlu yüzey kazanır.
- `hr`, görsel ve iframe öğeleri kart tasarım diline uyumlu ayraç, köşe ve kenarlık değerleri kullanır.
- Editörden gelen güvenli hizalama sınıfları ve izin verilen inline biçimler korunur.

## Erişilebilirlik ve güvenlik

- Mevcut `sanitizeTopicHtml()` süreci değişmez; yeni HTML yetkisi eklenmez.
- Bağlantıların mevcut güvenli `rel` ve `target` davranışı korunur.
- Renkler mevcut tema değişkenlerinden alınır ve karanlık temada ayrıca doğrulanır.
- Odak göstergeleri yalnızca renge bağlı kalmaz.

## Doğrulama

- PHP sözdizimi kontrolü yapılır.
- Örnek bir `updated_at` değeri `9 Ağustos 2026` olarak görünmelidir.
- Boş ve geçersiz tarihte güncelleme satırı görünmemelidir.
- Masaüstünde kartlar breadcrumb ile aynı genişlikte kalırken metin blokları `900px` sınırını aşmamalıdır.
- Görsel, iframe ve tablo içerikleri metin sınırından bağımsız çalışmalıdır.
- `390px` mobil görünümde sayfa yatay taşmamalıdır.
- Açık ve karanlık temada başlıklar, bağlantılar, listeler, alıntılar, tablolar, kod blokları ve ayraçlar okunabilir olmalıdır.

## Kapsam dışı

- Otomatik içindekiler
- Sürüm geçmişi
- Yeni editör araçları
- Yönetim panelinde tipografi ayarları
- Konu sayfalarının ortak stillerini değiştirmek
