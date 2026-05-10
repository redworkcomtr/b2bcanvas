# Geliştirme Notları — Mevcut Sistemin İncelemesi ve İyileştirme Önerileri

> Bu doküman, incelenen B2B sipariş portalının kullanıcı deneyimi, fonksiyonellik, performans ve teknik açıdan tespit edilen eksikliklerini ve klonu geliştirilirken eklenmesi önerilen iyileştirmeleri içerir. Bulgular, "Support My Great Canvas" tenant'ı altında support@mygreatcanvas.com hesabıyla yapılan gezinti sırasında gözlemlenmiştir.

---

## 1. Kullanıcı Deneyimi (UX) Eksiklikleri

### 1.1 Sipariş Listesi
- **Boş state ekranı yetersiz.** Liste ilk açıldığında veriler gelene kadar başlık ve sütun satırı görünüyor, ama "henüz veri yükleniyor" geri bildirimi sadece üstteki ince progress bar. Skeleton loader veya açık bir spinner çok daha iyi olur.
- **Filtre kalıcılığı yok.** Status filtresini seçip detaya girip geri dönünce filtre kayboluyor. URL query string'e yazılmalı (`?status=verified&page=2`).
- **Çoklu seçim yok.** Birden fazla siparişi toplu olarak iptal etme, etiket basma, dışa aktarma için checkbox sütunu yok.
- **Export yok.** Liste verisini CSV/Excel'e indirme butonu eksik. B2B'de muhasebe/raporlama için kritik.
- **Tarih aralığı filtresi yok.** Sadece status ve metin araması var; "son 30 gün", "Mayıs 2026" gibi tarih aralığı seçimi yok.
- **Notes sütunu boş gözüküyor.** Hiçbir siparişte not yok ama sütun yer kaplıyor — dinamik gizleme veya tooltip yapılabilir.
- **Items sütunu kırpılıyor.** Uzun ürün adları "..." ile bitiyor; tooltip ile tam içerik gösterilmeli.

### 1.2 Sipariş Detayı
- **Sipariş durum geçmişi/timeline yok.** "verified" denilen siparişin ne zaman submit edildiği, ne zaman doğrulandığı, üretime ne zaman alındığı görünmüyor. Bir status timeline (Shopify benzeri) çok kıymetli olur.
- **Notes alanı sipariş seviyesinde editlenebilir değil.** Müşterinin sipariş üzerine sonradan not eklemesi (örn. "lütfen 1 Haziran'dan önce kargolanmasın") doğrudan mümkün değil — ticket açmak gerekiyor.
- **Toplam ücret görünmüyor.** Order detayda subtotal, shipping, tax, total breakdown yok. Müşteri ne kadar borçlandığını / faturalandığını göremiyor.
- **Tracking number tıklanabilir değil.** Shipped siparişlerde tracking number göründüğünde direkt kargo sitesine (FedEx/UPS) deep-link açılmalı.
- **Print images ve Design images yan yana karşılaştırılamıyor.** İki sekme arasında geçiş yerine slider veya before/after viewer eklenebilir.
- **PDF/baskı dosyası indirme yok.** Müşteri kendi yüklediği design dosyalarını sonradan tekrar indiremiyor; sadece thumbnail görüyor.

### 1.3 Yeni Sipariş Wizard'ı
- **Draft kaydedilmiyor.** Adım 2'de form doldurulurken sayfa kapanırsa her şey kaybolur. Auto-save (localStorage + backend draft) eklenmeli.
- **Adımlar arasında gezinme zayıf.** Adım 4'ten 2'ye dönüp tekrar 4'e gelince önceki veriler bazen kaybolur (state yönetimi sorunu — klonda NgRx/Redux ile çözülmeli).
- **Validation feedback geç.** Bazı zorunlu alanlar (Frame Options) Step 2'de işaretli ama Next butonu pasif olunca neden pasif olduğunu anlamak için scroll gerekiyor. "1 hata kaldı" şeklinde fixed alert eklenmeli.
- **Bulk ürün ekleme yok.** Tek siparişte 5 farklı ürün eklemek için her birini ayrı ayrı "Select product → Select" döngüsü gerekiyor. "Aynı türden 10 farklı boyut" gibi durumlar için bulk picker olmalı.
- **Şablon indirme tek tek.** Her ürünün design template'ini ayrı ayrı indirmek gerekiyor; bir order'daki tüm şablonları zip olarak indirme butonu eklenmeli.
- **Önizleme yok.** Yüklenen design image'i panel boyutunda nasıl görüneceği önizlenmiyor. Bleed/safe area overlay'i olmadan müşteri kötü baskı alma riski taşıyor.

### 1.4 Product Mapping
- **Operator sadece "equals".** Gerçek hayatta `contains`, `starts with`, `regex`, `not equals` gibi operatörler gerekli. Örn: SKU prefix'i ile eşleştirme.
- **Test/preview butonu yok.** Kural yazıldıktan sonra "Bu kural mevcut siparişlerimden hangilerine uyar?" preview'u olmalı.
- **Toplu mapping import/export yok.** 1000+ ürünü tek tek mapping ile eklemek zor; CSV ile import edilmeli.
- **Mapping önceliği belirsiz.** Birden fazla kural aynı item'a uyarsa hangisi seçilir? Sortable priority kolonu eklenmeli.
- **Fulfillment SKU alanı kafa karıştırıcı.** "SKU" ve "Fulfillment SKU" arasındaki fark UI'da açıklanmamış (info tooltip eksik).

### 1.5 Issues / Tickets / Claims
- **Üç ayrı modül ama tek veri modeli gibi davranıyor.** Filtreler ve sütunlar aynı; tek bir "Issues" inbox'ında tab/filter ile ayırmak hem mental load'u azaltır hem geliştirme süresini.
- **Yorumlara real-time yenileme yok.** Üretici yorum bıraksa müşteri sayfayı yenilemeden görmez. SSE/WebSocket ile push gerekli.
- **Attachment preview yok.** Yüklenen görseller modal lightbox ile değil, sadece thumbnail olarak gösteriliyor.
- **Mention/etiketleme yok.** Yorumlarda `@kullanıcı` ile etiketleme veya ekibe yönlendirme yapılamıyor.
- **Read receipts yok.** Müşteri yazdığı yorumu üreticinin gördüğünü anlayamıyor.
- **SLA/yanıt süresi yok.** Bir ticket'ın ne kadar süredir açık olduğu görünüyor ama SLA hedefi ve "geçmiş" uyarısı yok.

### 1.6 Required Actions
- **"Closed" durumundaki action'lar listeyi şişiriyor.** 41 action'un büyük çoğunluğu kapalı, açık olan sadece 1 tane. Default filtre "Open" olmalı.
- **Bulk resolve yok.** Aynı ürün için açılmış 10 farklı action'ı tek bir mapping ile çözebiliyorsanız bulk işlem butonu olmalı.
- **Action'ın hangi siparişe ait olduğunu görmek için her birinin detayına girmek gerekiyor.** Description'da order number var ama tıklanır olmalı.

### 1.7 Genel UI
- **Mobil uyumlu değil görünüyor.** Sidebar her zaman açık, geniş tablolar mobil ekranda yatay kayar. Responsive breakpoint'ler ve hamburger menu eksik.
- **Dark mode yok.** B2B kullanıcısı genelde uzun saatler çalışır; dark mode opsiyonu performans değil konfor meselesi.
- **Klavye kısayolu yok.** "n" ile yeni sipariş, "/" ile arama, "g s" ile orders, vb. power-user kısayolları yok.
- **Breadcrumb yok.** Order detayında "Orders → 16611" gibi breadcrumb olmadığı için geri butonu (sol üst ok) tek yol.
- **Sidebar daraltma yok.** Sidebar her zaman aynı genişlikte; daraltılabilir/expandable olmalı.
- **Hesap menüsü zayıf.** Sol üstte "SM" avatar var ama tıklanır profil sayfası yok. Sadece "Sign out" linki ve gear icon var.
- **Tenant adı kırpılıyor.** "Support My Great Ca / nvas" şeklinde 2 satıra bölünmüş — overflow/ellipsis problemi.

---

## 2. Fonksiyonel Eksikler

### 2.1 Sipariş Yönetimi
- **Sipariş düzenleme yok.** Yeni sipariş oluşturduktan sonra ürünleri değiştirme (qty güncelleme, opsiyon değiştirme) yok; sadece item silme var. Sıklıkla istenen "miktar +1" işlemi için yeni order açmak gerekiyor.
- **Duplicate order yok.** Önceki bir siparişi şablon olarak alıp yeni sipariş oluşturma yok ("Reorder" butonu).
- **Re-order suggestions yok.** Sık alınan ürünler için "favoriler" veya "tekrar sipariş ver" listesi yok.
- **Order tagging yok.** Müşterinin kendi sınıflandırması (örn. "Etsy", "Shopify", "iade") için etiket sistemi yok.

### 2.2 Ödeme / Faturalandırma
- **Fiyat görünmüyor.** Order detayında subtotal/total yok (sadece New Order wizard'ında var). Müşterinin geçmiş aylık ciro/maliyet görmesi mümkün değil.
- **Fatura/invoice yok.** Bir siparişin PDF faturası indirilemiyor.
- **Statement yok.** Aylık özet, vergi raporu, dönem bazlı CSV indirme yok.
- **Otomatik ödeme tanımlama yok.** Kayıtlı kart, otomatik tahsilat yapılandırması görünmüyor.
- **Kredi limiti/postpaid yok.** B2B'de yaygın olan "ay sonu faturalandır, 30 gün vadeli öde" akışı yok.

### 2.3 Entegrasyonlar
- **API anahtarı/Webhook yönetimi yok.** Müşteri Shopify/Etsy/Woo ile direkt entegre etmek istese arayüzde API token üretme yeri yok. (Backend'de var olabilir ama UI'da görünmüyor.)
- **Webhook subscriber yok.** "Sipariş durumu değişince benim sistemime POST at" özelliği yok. B2B'de kritik.
- **Native Shopify/Etsy connector yok.** Manuel CSV upload yerine direkt mağaza bağlantısı çok değerli olur.

### 2.4 Raporlama / Analytics
- **Dashboard'da metrik yok.** Ana ekran sadece modül kartları içeriyor; "bugün gelen sipariş", "hata oranı", "ortalama üretim süresi" gibi KPI'lar yok.
- **Chart/grafik yok.** Sipariş trendleri, en çok satan ürünler, mapping eksiklik oranı görselleştirilmiyor.
- **Conversion funnel yok.** submitted → verified → shipped oranları takip edilmiyor (müşteri açısından).

### 2.5 Toplu İşlemler (Bulk)
- **Toplu iptal yok.**
- **Toplu adres düzeltme yok.** Yanlış zip code ile gelen 50 sipariş için tek tek edit gerekiyor.
- **Toplu ticket açma yok.**
- **Toplu re-validate yok.** Mapping yapıldıktan sonra önceki failed siparişleri otomatik re-process etme yok.

### 2.6 Kullanıcı Yönetimi
- **Tek kullanıcılı görünüm.** Aynı tenant altında birden fazla kullanıcı ekleme (örn. operasyon ekibinden 3 kişi) imkanı UI'da yok.
- **Rol/yetki ayrımı yok.** Tüm tenant kullanıcıları her şeyi yapabiliyor görünüyor — finans, operasyon, designer ayrımı gerekli.
- **Activity log yok.** "Kim ne zaman ne yaptı" şeffaflığı yok.

### 2.7 Bildirimler
- **Sadece e-posta var.** SMS, Slack, MS Teams, webhook gibi alternatif bildirim kanalları yok.
- **In-app notification merkezi yok.** Üst bardaki çan ikonu ve okunmamış sayacı yok.
- **Granüler abonelik yok.** "Sadece 1000$ üstü siparişler için bildir" gibi koşullu kural yok.

### 2.8 Dosya Yönetimi
- **Versiyon kontrolü yok.** Aynı panele yeni bir design yüklersek eski versiyonu görmek mümkün değil.
- **Format validation eksik.** DPI, color profile (CMYK/RGB), bleed kontrolü otomatik yapılmıyor — kötü dosya silently kabul ediliyor.
- **Toplu upload zayıf.** 50 panel için 50 ayrı "Upload" butonu — drag & drop zip / multi-file selection yok (gözlemlenmedi).

---

## 3. Performans Gözlemleri

- **İlk yüklemede orders listesi boş gözüktü.** İlk request'ten yanıt geldi ama UI 2-3 saniye geç render etti. Muhtemelen change detection veya hydration sorunu. Klonu Angular 17+ ile build ediyorsak `OnPush` change detection ve signal'lar şart.
- **Order detayında "Loading product info..." kalıcı.** Product detayı asenkron geliyor ama bazı ürünlerde hiç gelmiyor (action_needed durumundaki siparişte). Skeleton'dan sonra timeout + fallback mesajı gerekli.
- **Mapping listesi tek seferde tüm kayıtları çekiyor gibi.** Sayfalama parametresi gözükmüyor; 10k+ mapping olunca yavaşlama beklenir. Server-side pagination + virtual scroll önerilir.
- **Görsel thumbnail'leri optimize edilmemiş.** Doğrudan tam çözünürlük URL'leri çağrılıyor olabilir. CDN üzerinde otomatik resize (örn. Cloudflare Image Resizing) eklenmeli.
- **CORS pattern.** Tüm istekler ayrı subdomain'e (`api.rvprintfactory.com`) gidiyor; preflight OPTIONS sayısı yüksek olabilir. Klonda backend ile frontend aynı subdomain altında ya da credential'lı caching ile optimize edilebilir.

---

## 4. Güvenlik Gözlemleri

- **Şifre politikası bilinmiyor.** Login formunda visible güç göstergesi yok. Klonda min 12 karakter + breach check eklenmeli.
- **MFA/2FA görünmüyor.** B2B portallarında 2FA standart olmalı.
- **Session timeout görünmüyor.** Tarayıcı uzun süre açık kaldıktan sonra otomatik logout yok gibi görünüyor.
- **Audit log yok.** Müşteri açısından "kim bu siparişi iptal etti / kim adresi değiştirdi" görünmüyor.
- **CSP/HSTS header'ları kontrol edilmeli.** Klonda strict CSP, Permissions-Policy, X-Content-Type-Options, HSTS preload zorunlu.
- **Tenant izolasyonu testi gerekli.** URL'de UUID değiştirilince başka tenant siparişi açılıyor mu? Klonu yazarken bu attack vector'üne karşı integration test yazılmalı.

---

## 5. Veri Modeli / API Tasarımındaki İyileştirme Fırsatları

- **Order ID iki ayrı format görünüyor.** Numerik kısa ID'ler (16611) iç üretici sıra numarası, alfanumerik ID'ler (1778370222NBTX) muhtemelen import/external. Karışıklığa sebep oluyor. Klonda **public order number** (görünen) ve **internal id** (uuid) ayrımı net olmalı + tablo sütunlarında "Customer Reference" ayrı görünmeli.
- **Status taxonomy fazla.** 12 farklı durum (action_needed, validation_failed, on_hold, vb.) müşteri için kafa karıştırıcı. Klonda "müşteri-dostu kısa label" + arkada teknik durum saklanabilir.
- **Issue/Ticket/Claim/RequiredAction ayrımı muğlak.** Hepsi aynı şablonu kullanıyor ama farklı modüllerde. **Polymorphic issues** tablosu + `category` enum (support / claim / system_action / mapping) tek bir görünümde sergilenebilir, gerekirse filter ile ayrılır.
- **clientOrderId unique mı, tenant scope'unda mı?** Aynı clientOrderId ile re-import yapılırsa ne olur? Klonda upsert vs duplicate-error davranışı net belirtilmeli.
- **Mapping rules JSON şeması belirsiz.** Operator'lar genişletilebilir olduğu için klonda formal JSON Schema + UI generator tutmak iyi olur.
- **`@DIT_1` gibi ticket order number'ları gözlemlendi (örn. 16369@DIT_1).** Muhtemelen tracking için iç ek-not. Klonda `subOrderRef` ayrı bir sütun olarak temsil edilmeli.

---

## 6. Yeni Özellik Önerileri (Klonda Eklenebilecek Değer Katanlar)

| Öncelik | Özellik | Açıklama |
|---|---|---|
| 🔥 Yüksek | Real-time bildirim merkezi | Üst barda çan ikonu, in-app push, masaüstü notification |
| 🔥 Yüksek | Live preview / mockup | Müşterinin yüklediği görseli oda mockup'ında veya ürün üzerinde önizleme (var olan "Float Mount" görselleri üzerine kompozit) |
| 🔥 Yüksek | Otomatik dosya validasyonu | DPI, renk profili, bleed kontrolü; uygun değilse upload reddedilir veya uyarı verilir |
| 🔥 Yüksek | Toplu re-process | Mapping kurulduktan sonra failed siparişleri otomatik tekrar çalıştır |
| 🔥 Yüksek | API token + webhook yönetimi UI | Self-service entegrasyon (Shopify, Etsy, custom) |
| ⚡ Orta | Native Shopify / WooCommerce / Etsy connector | Tek tıkla mağaza bağlama, otomatik sipariş senkronu |
| ⚡ Orta | Faturalandırma & istatement | PDF fatura, aylık özet, KDV/vergi raporu |
| ⚡ Orta | Multi-user / RBAC | Tenant içinde rol bazlı yetkilendirme |
| ⚡ Orta | Activity log | Tenant düzeyinde audit trail görünümü |
| ⚡ Orta | Dashboard analitik | Sipariş trendi, hata oranı, ortalama üretim süresi, en çok satan ürünler |
| ⚡ Orta | Toplu işlemler | Bulk cancel/edit/tag/export |
| ⚡ Orta | Order tags / filtreler | Müşteri kendi etiketlerini ekleyebilsin |
| 💡 Düşük | Dark mode + tema | Power user konforu |
| 💡 Düşük | Klavye kısayolları | Cmdk-style command palette + shortcuts |
| 💡 Düşük | Saved searches / smart filters | "Bu hafta gönderilen tüm Framed Canvas'lar" |
| 💡 Düşük | i18n (TR/EN/DE) | Çoklu dil desteği |
| 💡 Düşük | AI asistan | "Bu siparişin durumu nedir? / 16611 nolu sipariş için ticket aç" doğal dil arayüzü |

---

## 7. Mobile / Erişilebilirlik (a11y) Notları

- Sidebar her zaman görünür → mobilde drawer'a dönüştürülmeli.
- Tablolarda yatay scroll → mobil için card layout veya kritik kolonları gösteren sıkıştırılmış görünüm.
- Tıklama hedefleri (more_vert butonları) 24x24 px, mobil için min 44x44 px hedef ölçüsüne çıkarılmalı.
- Renk kontrastı: "action needed" turuncu yazısı açık zeminde marjinal (WCAG AA için kontrol gerekli).
- Form alanlarında `aria-label` ve `aria-describedby` kontrol edilmeli.
- Klavye ile menu açma/kapama (Escape) çalışıyor görünüyor; ancak focus trap order detayda zaman zaman kayboluyor.

---

## 8. Test ve Kalite Önerileri

- **E2E test eksikliği gözleniyor.** Bazı edge case'ler (mapping başarısız → re-process) zayıf. Klonda Playwright ile critical user journeys'i kapsayan smoke test'ler şart.
- **API contract testleri yok.** Frontend ve backend arasında schema validation yok. OpenAPI veya tRPC + Zod ile typed-end-to-end yaklaşımı denenebilir.
- **Load test yok.** 10k sipariş listesi performansı, 1k satırlık CSV import'u edge case'leri görülmedi.
- **Internationalization test yok.** Uzun Türkçe/Almanca metinler arayüzü kırıyor olabilir.

---

## 9. Operasyonel / DevEx İyileştirmeleri

- **Feature flag sistemi.** LaunchDarkly / Unleash veya basit DB tablosu ile yeni özellikleri kademeli açma.
- **Staging ortamı.** Müşteri tarafından test edilebilir bir sandbox.
- **API sandbox.** Geliştiriciler için test API anahtarı + fake sipariş üretimi.
- **Health/status sayfası.** statuspage.io benzeri public uptime/status sayfası.
- **Backup & disaster recovery.** Günlük DB snapshot + point-in-time recovery; S3 cross-region replication.

---

## 10. Kısa Özet — Klonda Mutlaka Dikkat Edilecekler

1. **Multi-tenant + RBAC** baştan iyi düşün — sonradan eklemek çok pahalı.
2. **Order ID şeması net olsun** (public number + internal uuid + client reference).
3. **Status taxonomy'i sade tut** — müşteri-dostu label + arka plan technical state.
4. **Mapping operator'larını genişletilebilir yap** (equals + contains + starts_with + regex).
5. **Bulk operations'ı v1'de planla**, sonradan eklemek mimari değişikliği gerektirir.
6. **Real-time bildirim (SSE/WebSocket)** v1'de olsun.
7. **Dosya validasyonu** (DPI/bleed/color profile) backend tarafında zorunlu — silently accept etme.
8. **Audit log** her kritik mutation için yaz; UI'ya sonradan eklenir.
9. **API-first yaklaş**: önce REST/RPC + OpenAPI, sonra UI. Müşterinin entegre etmek isteyebileceği her şey API'da olsun.
10. **Test piramidi**: %70 unit, %20 integration, %10 E2E hedefle.

---

Bu notlar mevcut sistemde gözlemlenen davranışlara dayanır. Klonun ilk sürümünde tümünü uygulamak yerine, **§6'daki "Yüksek öncelik" özelliklerini** v1.0'a, ortaları v1.1'e, düşükleri v2.0'a planlamak öneriliyor.
