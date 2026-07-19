# Public Notification Channel Preferences Design

## Goal

Public Bildirim Merkezi > Tercihler ekraninda kullaniciya iki alt sekme sunulacak:

- Site Ici Bildirimler
- E-posta Bildirimleri

Bu iki kanal birbirinden bagimsiz calisacak. Kullanici ayni bildirim olayini site icinde acik, e-postada kapali; ya da tersine site icinde kapali, e-postada acik kullanabilecek.

## Current Context

Bildirim merkezi su anda `includes/src/Modules/Notifications/Http/notifications-page-content.php` icinde Tercihler ekranini render ediyor. Tercihlerin kalici kaydi `user_settings` tablosuna yapiliyor.

Bildirim davranisi module servisleri tarafindan yonetiliyor:

- `NotificationPreferenceService` bildirim olaylarini, kullanici ayarlarini ve SQL filtrelerini yonetiyor.
- `NotificationCenterService` header acilir listesi ve rozet davranisinda kullanici tercihlerini okuyor.
- `NotificationDispatchService` olay bildirimi olustururken kullanici tercihlerini ve e-posta kuyrugunu dikkate aliyor.

Mevcut site ici anahtarlar korunacak:

- `notif_group_header`
- `notif_browser_push`
- `notif_type_info`
- `notif_type_success`
- `notif_type_warning`
- `notif_type_error`
- `notif_group_events`
- `notif_event_*`

Mevcut e-posta genel anahtarlari korunacak:

- `notif_group_email`
- `notif_email_updates`

## Proposed Approach

Tercihler ekranindaki alt sekmeler iki kanala indirgenecek:

1. Site Ici Bildirimler
2. E-posta Bildirimleri

Site ici sekmesi mevcut gorunurluk ve bildirim olayi tercihlerini kullanacak. E-posta sekmesi ise ayni bildirim olaylari icin ayri e-posta tercih anahtarlari kullanacak.

Yeni e-posta olay anahtar formati:

`notif_email_event_<event_key>`

Ornekler:

- `notif_email_event_comment_on_topic`
- `notif_email_event_comment_reply`
- `notif_email_event_direct_message_received`
- `notif_email_event_topic_approved`

Default deger, ilgili olay tanimindaki mevcut `default` degerinden gelecek. Boylece eski kullanicilar icin davranis beklenmedik sekilde sessize alinmayacak.

## Interface Design

Tercihler formunun ustunde segment/tab kontrolu yer alacak.

Site Ici Bildirimler sekmesi:

- "Site ici bildirimleri aktif tut" grup anahtari `notif_group_header`
- Header bildirim merkezi anahtari `notif_browser_push`
- Bilgi, basari, uyari ve kritik bildirim tipi anahtarlari
- Site etkinligi anahtari `notif_group_events`
- Mevcut `notif_event_*` olay tercihleri
- Okuma deneyimi ayarlari: `notif_auto_mark_on_open`, `notif_compact_view`

E-posta Bildirimleri sekmesi:

- "E-posta bildirimlerini aktif tut" grup anahtari `notif_group_email`
- E-posta teslimi anahtari `notif_email_updates`
- Ayni olay listesi icin `notif_email_event_*` anahtarlari

Mobilde mevcut kart/switch yapisi korunacak. Tablar sarabilir, ayar satirlari mevcut responsive grid davranisini kullanir.

## Data Flow

Kaydetme:

1. Kullanici tercihleri formu POST eder.
2. `notification_user_setting_keys()` hem site ici hem e-posta anahtarlarini toplar.
3. Her anahtar `user_settings` tablosuna `1` veya `0` olarak upsert edilir.
4. "Onerilen ayarlara don" akisi yeni e-posta olay anahtarlarini da default degerlere ceker.

Site ici dispatch:

1. `NotificationDispatchService` site ici uygunlugu icin mevcut `notif_group_events`, `notif_event_*` ve `notif_type_*` kontrollerini hesaplar.
2. E-posta uygunlugu yoksa ve site ici uygunlugu da yoksa dispatch no-op yapar.
3. Notification kaydi olustugunda `delivery_channels` metadatasinda site ici kanalinin acik olup olmadigi dogru yazilir.
4. `NotificationCenterService` mevcut `notif_group_header`, `notif_browser_push`, tip ve olay filtrelerini kullanarak header/dropdown listesini filtreler.
5. Bildirim merkezi liste sorgusu ayni site ici tercih filtresini kullanir.

E-posta dispatch:

1. Notification kaydi olustuktan sonra e-posta kuyrugu icin ayri kontrol yapilir.
2. `notif_group_email` ve `notif_email_updates` acik olmali.
3. Admin tarafinda ilgili template icin `email_enabled` ve global `notif_email_channel_ready` acik olmali.
4. Olay bazli `notif_email_event_<event_key>` acik olmali.
5. Sartlar saglanirsa `notification_email_queue` kaydi olusturulur.

Not: Ilk fazda site ici kapali ama e-posta acik olan bir olay icin notification kaydi olusturulmasi gerekir; cunku e-posta kuyrugu mevcut mimaride `notification_id` foreign key ile calisiyor. Bu nedenle dispatch erken donusu sadece site ici tercihine bagli kalmayacak; in-app ve e-posta kanal uygunlugu birlikte degerlendirilecek.

## Error Handling

- CSRF dogrulamasi mevcut form akisi ile korunacak.
- Gecersiz veya eksik ayar anahtarlari kaydedilmeyecek; sadece tanimli anahtar listesi kullanilacak.
- E-posta tercih anahtari bulunamazsa default deger olay tanimindan okunacak.
- Admin e-posta kanali kapaliysa kullanici e-posta tercihleri acik olsa bile kuyruk olusmayacak.
- E-posta kuyrugu basarisiz olursa mevcut servis loglama davranisi korunacak.

## Testing

Odak testler:

- PHP syntax kontrolu degisen PHP dosyalari icin calistirilacak.
- Site ici kapali, e-posta acik senaryosunda dispatch notification kaydi ve e-posta kuyrugu mantigini bozmayacak.
- E-posta kapali, site ici acik senaryosunda notification kaydi olusacak ama e-posta kuyrugu olusmayacak.
- Tercihler POST akisi yeni `notif_email_event_*` anahtarlarini kaydedecek.
- Tercihler ekraninda iki alt sekme gorunecek ve JS tab gecisi mevcut `data-notification-settings-tab` davranisi ile calisacak.
