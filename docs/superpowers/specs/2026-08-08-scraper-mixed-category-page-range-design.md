# Karma Kategori Sayfa Aralığı ve Boş Durum Tasarımı

## Amaç

Admin panelindeki `Tüm Kategorilerden İçerik Çek (Karma)` akışında başlangıç ve bitiş değerlerini her eşlemenin hedef kategori sayfalarına bağımsız olarak uygulamak. Seçilen aralıkta çekilmeye hazır içerik kalmadığında bunun nedenini standart bir empty state ile açıkça göstermek.

## Kapsam

- Karma listelemedeki başlangıç ve bitiş sayfası semantiğini kesinleştirmek.
- Her hedef kategori için tarama sonucunu ve içerik sayılarını izlemek.
- Daha önce çekilmiş içeriklerle gerçekten içerik bulunmayan sayfaları ayırmak.
- Yeni içerikleri mevcut karma konu kartlarında göstermeye devam etmek.
- Kategori sonuçlarını kompakt bir özet halinde sunmak ve gerekli olduğunda genel empty state göstermek.

Tek kategoriye ait `Toplu İçerik Çek` akışı, scraper API sözleşmesi, içerik çekme/aktarma davranışı ve duplicate ayarları bu değişikliğin dışındadır.

## Sayfa Aralığı Semantiği

`Başlangıç = 3` ve `Bitiş = 5` seçildiğinde her aktif eşleme için o eşlemenin uzak hedef kategori URL'sindeki 3, 4 ve 5. sayfalar tarama kapsamına girer. Aralık, tüm kategorilerden oluşan birleşik bir sonuç listesinin sayfaları veya yerel kategori sayfaları anlamına gelmez.

Scraper kaynak sitelerdeki sayfa URL şablonlarını varsaymayacak. Hedef sayfaya ulaşmak için ilk kategori URL'sinden başlayıp API'nin döndürdüğü `next_url` bağlantılarını izleyecek. Başlangıçtan önceki sayfalar yalnızca gezinmek için okunacak; bu sayfalardaki konular sonuca, sayaçlara veya duplicate değerlendirmesine dahil edilmeyecek.

Kullanıcı başlangıç değerini bitişten büyük girerse mevcut güvenli davranış korunacak ve iki değer küçükten büyüğe normalize edilecek. Her iki değer istemci tarafında da `1..999` aralığına sıkıştırılacak; HTML `min` ve `max` nitelikleri tek başına güvenlik sınırı sayılmayacak.

## Tarama Yaşam Döngüsü

Aynı anda yalnızca son başlatılan karma kategori taraması geçerli olacak. Kullanıcı tarama sürerken yeniden `Tüm Kategorileri Listele` düğmesine bastığında:

1. devam eden taramanın `AbortController` örneği iptal edilir;
2. güncel sayfa aralığı ve site filtresi yeniden okunur;
3. yeni bir tarama kimliği ve `AbortController` ile tarama hemen başlatılır;
4. iptal edilmiş veya daha eski taramalardan dönen sonuçlar state, loading alanı ya da sonuç HTML'ini değiştiremez.

`apiPost` mevcut çağrıları bozmadan isteğe bağlı fetch seçenekleri kabul edecek ve `signal` değerini `adminFetchJson` çağrısına iletecek. İptal, kullanıcıya ağ hatası olarak gösterilmeyecek. Ağ isteğinin iptal edilemediği bir ortamda tarama kimliği yine eski sonucun güncel sonucu ezmesini engelleyen ikinci güvenlik katmanı olacak.

## Sonuç Modeli

Karma tarama durumu genel konu listesinin yanında her eşleme için aşağıdaki bilgileri tutacak:

- eşleme, site ve yerel kategori kimliği;
- istenen başlangıç ve bitiş sayfası;
- aralık içinde başarıyla taranan sayfa sayısı;
- bulunan toplam benzersiz konu sayısı;
- daha önce çekilmiş konu sayısı;
- kategori içinde çekilmeye hazır yeni konu sayısı;
- karma listeye global URL tekilleştirmesinden sonra eklenen yeni konu sayısı;
- başka bir eşleme tarafından karma listeye daha önce eklenmiş yeni konu sayısı;
- varsa tarama hatası;
- hedef kategori aralık bitmeden sona erdiyse ulaşılabilen son sayfa.

URL tekilleştirmesi karma liste genelinde korunacak. Bir URL birden fazla eşlemede görünürse ilk geçerli eşleme üzerinden yalnızca bir kez listelenecek. Kategori özetindeki bulunan ve daha önce çekilmiş sayaçları her kategorinin kendi tarama sonucunu yansıtacak; `yeni içerik` sayısı ise ekrandaki kartlarla tutarlı olması için karma listeye gerçekten eklenen sayıyı gösterecek. Başka bir eşlemede listelenmiş yeni URL'ler ayrıca tekrar sayısı olarak tutulacak.

## Veri Akışı

1. Aktif eşlemeler site filtresine göre belirlenir.
2. Her eşleme kendi `remote_category_url` değerinden başlatılır.
3. `next_url` takip edilerek bitiş sayfasına kadar ilerlenir.
4. Yalnızca seçilen aralıktaki API sonuçları normalize edilir.
5. Her sonuç kategori bazında `daha önce çekilmiş` veya `yeni` olarak sayılır.
6. Daha önce çekilmiş konular mevcut davranışta olduğu gibi karma konu kartlarına eklenmez.
7. Yeni ve global olarak benzersiz konular karma listeye eklenir.
8. Tüm eşlemeler tamamlandığında kategori özetleri ile karma liste birlikte render edilir.

## Arayüz Davranışı

Sonuç alanının üst kısmında seçilen hedef sayfa aralığı açıkça `Her hedef kategoride 3-5. sayfalar` biçiminde belirtilir. Böylece girişlerin birleşik liste sayfalaması olmadığı görünür olur.

Her kategori için tek satırlık kompakt bir durum özeti gösterilir:

- yeni içerik varsa: kategori adı ve karma listeye eklenen yeni içerik sayısı;
- yalnızca daha önce çekilmiş içerik varsa: kategori adı ve daha önce çekilmiş içerik sayısı;
- yeni içerik başka bir eşlemede listelenmişse: kategori adı ve tekrarların karma listede zaten yer aldığı bilgisi;
- hiç konu bulunmadıysa: kategori adı ve içerik bulunamadı durumu;
- tarama başarısızsa: kategori adı ve hata durumu.

Bir kategori aralığın bir bölümünden yeni içerik topladıktan sonra hata verirse aynı satır hem `Tarama hatası` hem de karma listeye eklenen yeni içerik sayısını gösterir. Çekilmiş ve tekrar eden içerik sayaçları da mevcutsa korunur.

En az bir yeni içerik varsa mevcut seçim kontrolleri ve karma konu kartları gösterilir. Boş kategoriler büyük kartlar üretmez; yalnızca kompakt özet satırında yer alır.

Listeleme tamamlandıktan sonra kullanılan ilerleme bileşeni içerik aktarma işlemi yapılmadığı için `başarılı/hatalı` aktarım sayaçlarını göstermez. Bunun yerine listelenen konu sayısı ile yeni, daha önce çekilmiş ve hatalı kategori özeti gösterilir. İçerik çekme aşamasındaki gerçek aktarım ilerlemesi mevcut başarılı/hatalı sayaçlarını kullanmaya devam eder.

Hiç yeni içerik yoksa standart admin empty-state görünümü kullanılır:

- Aralıkta konu bulundu fakat tamamı daha önce çekildiyse başlık `Çekilecek yeni içerik kalmadı`, açıklama ise seçilen hedef kategori sayfalarındaki tüm içeriklerin daha önce çekildiğini belirtir.
- Hiçbir kategoride konu bulunmadıysa başlık `Seçilen sayfalarda içerik bulunamadı` olur.
- Sonuç alınamamasının tek nedeni tüm taramaların başarısız olmasıysa hata tonlu durum gösterilir ve kategori hata özetleri korunur.

Empty state, seçilen aralığı, taranan kategori sayısını ve daha önce çekilmiş içerik sayısını meta alanında gösterir. Yeni içerik yokken seçim ve çekim kontrolleri render edilmez.

## Hata ve Kısmi Sonuçlar

Bir kategorideki ağ veya ayrıştırma hatası diğer kategorilerin taranmasını durdurmaz. Hatalı kategori sonuç özetinde işaretlenir; başarıyla bulunan diğer kategorilerin yeni içerikleri listelenmeye devam eder.

Bir hedef kategoride `next_url` seçilen başlangıç sayfasına ulaşmadan biterse kategori `istenen aralığa ulaşılamadı` olarak belirtilir. Aralığın bir bölümü tarandıktan sonra sayfalama biterse ulaşılabilen sayfalardan elde edilen sonuçlar korunur ve özet gerçek taranan sayfa sayısını gösterir.

Aktif eşleme bulunmaması mevcut uyarı akışını korur fakat standart admin empty-state görünümüyle sunulur.

## Uygulama Sınırları

- Ana değişiklik `admin/assets/scraper.js` içindeki karma kategori state, tarama ve render fonksiyonlarında yapılır.
- Dinamik içerik için mevcut admin empty-state sınıflarına uygun küçük bir JavaScript render yardımcısı kullanılabilir; PHP helper'ı istemci tarafında doğrudan çağrılmaz.
- Giriş etiketlerine aralığın hedef kategori sayfalarını ifade ettiğini açıklayan kısa yardımcı metin `admin/scraper.php` içinde eklenebilir.
- Yeni API endpoint'i veya veritabanı değişikliği gerekmez; `discover_urls` yanıtındaki `urls`, `next_url` ve import işaretleri yeterlidir.

## Doğrulama

- JavaScript sözdizimi ve değişen PHP dosyalarının lint kontrolleri çalıştırılır.
- `1-1`, `2-3` ve başlangıcın bitişten büyük girildiği aralıklar doğrulanır.
- `0`, negatif ve `999` üzerindeki değerlerin `1..999` aralığına sıkıştırıldığı doğrulanır.
- Her eşlemenin aynı aralığı kendi hedef kategorisinde bağımsız kullandığı kontrol edilir.
- Seçilen aralıkta yalnızca yeni içerik, yalnızca daha önce çekilmiş içerik, hiç içerik bulunmaması ve karışık sonuç senaryoları doğrulanır.
- Bir kategorinin hata verdiği kısmi başarı senaryosunda diğer sonuçların korunduğu kontrol edilir.
- Gecikmeli iki tarama başlatılarak ilk isteğin iptal edildiği ve geç kalan ilk yanıtın ikinci tarama state'ini veya HTML'ini değiştirmediği doğrulanır.
- Kısmi hata öncesinde yeni içerik bulan kategori satırında hata ve yeni içerik rozetlerinin birlikte gösterildiği doğrulanır.
- Listeleme sonucunda anlamsız `0 başarılı / 0 hatalı` aktarım sayaçlarının gösterilmediği doğrulanır.
- Başlangıç sayfasından önceki konuların sayaçlara ve karma listeye girmediği doğrulanır.
- Yeni içerik olmadığında seçim/çekim kontrollerinin bulunmadığı ve doğru empty-state metninin gösterildiği kontrol edilir.
- Masaüstü ve mobil görünümde kategori özetlerinin, empty state'in ve konu kartlarının çakışmadan görüntülendiği tarayıcı üzerinden doğrulanır.
