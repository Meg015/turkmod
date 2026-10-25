# Bildirim merkezi e-posta düzeltmeleri

Kapsam: Olay şablonlarının test gönderimi, e-posta kanalının ayrı yönetimi ve kullanıcının konusunun onay/yayım bildirimleri.

Mevcut kuyruk ve şablon hizmetleri korunur. Test gönderimi, hesap ve yönetici şablonlarında olduğu gibi doğrudan posta sürücüsünü kullanır; otomatik kuyruk kapalıyken de çalışır. Sonuç ancak sürücü başarılı dönerse başarılı gösterilir. Olay kartlarında aktiflikten bağımsız e-posta anahtarı bulunur.

`topic_approved` moderasyon onayını, `topic_published` doğrudan yayımlamayı temsil eder. Aynı işlem iki e-posta üretmez. Ön arayüzde doğrudan yayım, yönetici düzenlemesi ve toplu yayım kapsanır. Yayımdaki konunun sıradan düzenlenmesi yeni yayım olayı oluşturmaz. Alıcı konu sahibidir; kullanıcının kanal tercihleri korunur. Bu iki olayın e-postaları ve gerekli kuyruk ayarı etkinleştirilir; diğer kapalı şablonlar açılmaz.

Yeni varsayılanlar mevcut özel metinleri ezmeden eklenir. İki konu şablonunun e-posta metinleri site adı, alıcı adı ve konu bağlantısını içerir. Tüm varsayılan ve kayıtlı şablonlar değişkenler, boş içerik, bağlantılar ve HTML üretimi yönünden kontrol edilir.

Doğrulama: CLI regresyon testleri; izole veritabanında gönderim, tekrar engelleme, kullanıcı tercihi, kuyruk yeniden denemeleri; yerel SMTP alıcısıyla gerçek MIME aktarımı; PHP sözdizimi ve yönetici ekranı kontrolü. Harici kullanıcılara deneme e-postası gönderilmez. SMTP kabulü ile gelen kutusuna teslim farklı doğrulama seviyeleridir.
