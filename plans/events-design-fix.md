# Events Sayfası Tasarım Düzeltme Planı

## Amaç
Public taraftaki `/events` sayfasındaki CSS çakışmalarını temizlemek ve `ui-events-summary-cards` ile `ui-events-info-cards` alanlarının sıkışıklığını gidermek.

## Yapılacak Değişiklikler

### 1. `.ui-events-nav-container` Çakışmasını Giderme
- **Dosya**: `includes/src/Modules/Events/assets/css/events.css`
- **Satır 8586-8634**: Sticky glassmorphic nav tanımını KALDIR (grid tabs lehine)
- **Satır 9428-9522**: Grid tabs tanımını koru, gereksiz `!important` override'larını temizle

### 2. `.ui-events-summary-cards` Sıkışıklığını Giderme
- **Mevcut** (satır 6443-6448): `gap: 16px`, `margin-bottom: 24px`
- **Yeni**: `gap: 20px`, `grid-template-columns: repeat(4, 1fr)`
- `.ui-events-stat-card` padding: `20px → 24px`
- `.ui-events-stat-value` font-size: `1.6rem → 1.7rem`

### 3. `.ui-events-info-cards` Sıkışıklığını Giderme
- **Mevcut** (satır 6837-6842): `gap: 16px`, `minmax(280px, 1fr)`
- **Yeni**: `gap: 20px`, `minmax(300px, 1fr)`
- `.ui-events-info-card` padding: `16px → 20px`

### 4. `.ui-events-stat-card` Tutarlılık
- **Satır 7222-7235**: border-radius değişken kullan (`--ui-events-radius`)
- Kullanılmayan `.admin-stat-card` ile çakışma önlensin

### 5. `.ui-events-hero` Grid Boşluğu
- **Mevcut** (satır 57-63): `gap: 18px`
- **Yeni**: `gap: 24px`

### 6. Genel CSS Temizliği
- Gereksiz `!important` override'ları kaldır (özellikle `.ui-events-page` satır 7957-7961)
- Yinelenen `.ui-events-list-item` satır 368-377 ve 1833-1837 birleştirme

## Uygulama Adımları
1. events.css dosyasında yedek al
2. nav-container override çakışmasını düzelt
3. summary-cards gap/padding artır
4. info-cards gap/padding artır
5. stat-card border-radius standardize et
6. hero gap artır
7. Genel CSS override temizliği
8. events.min.css'i de güncelle

## Test
- `/events` sayfası tüm sekmelerde kontrol
- `/events/wheel`, `/events/raffle`, `/events/tasks`, `/events/rewards` alt sayfaları
- Mobil görünüm (≤768px) kontrolü
- Dark/Light tema geçişi