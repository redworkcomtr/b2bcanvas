# B2B Canvas - Eksiklik Raporu (2026-05-09)

## 1) Özet

Bu rapor, mevcut `main` branch üzerinde yapılan hızlı denetimin çıktılarını içerir.  
Modüller çalışır durumda olsa da örnek hedef site ile birebir akış eşleşmesi için bazı alanlar eksik veya sınırlı kalmıştır.

Rapor, yalnızca mevcut kod tabanından gözlenen durumun çıkarımıdır; hiçbir dosya henüz değiştirilmemişti.

## 2) Modül Bazında Eksik Durumlar

### A. Auth / Tenant / Permission
- Rol yönetimi backend’de `owner` destekli olsa da,
  - Ayarlar UI’sindeki rol seçenekleri owner’ı göstermiyor.
- Davet ve rol güncelleme ekranları bazı rollerde kilitli kalabiliyor.
- Sonuç: Owner erişimi yönetimi pratikte UI’dan eksik.

Etki dosyaları:
- `app/Models/User.php`
- `resources/js/views/settings/SettingsView.vue`

### B. Import Orders (CSV Intake + Import Motoru)
- Import ekranında örnek (`sample`) CSV metni frontend’de hardcoded.
- UI metni XLSX’i işaretlese de backend akışı CSV ağırlıklı:
  - Parser sadece CSV formatını işliyor.
  - Xlsx dönüşüm hattı ve üretimsel queue/scale yaklaşımı yok.
- Büyük dosya/queue optimizasyonu ve chunk işleme eksik.

Etki dosyaları:
- `resources/js/views/orders/ImportOrdersView.vue`
- `app/Services/CsvOrderImportParser.php`
- `app/Http/Controllers/Api/OrderController.php` (`importPreview`, `commitImport`)

### C. Required Actions / Çözümler
- İş akışı esas olarak `product_mapping_required` ve `address_error` etrafında çözülmüş durumda.
- Aksiyon türleri genişletilmiş görünse de (UI’da etiket/etiketleme), çözüm mantığı ve otomatik yeniden doğrulama her tipe yayılmıyor.
- `invalid_artwork`, `duplicate_order`, `product_unavailable` için çözümleme akışları sınırlı.

Etki dosyaları:
- `app/Services/RequiredActionWorkflowService.php`
- `app/Services/RequiredActionResolver.php`
- `resources/js/views/issues/IssuesView.vue` (aksiyon detay ve çözüm ekranı)

### D. Notifications (Event + Abonelik Yönetimi)
- Event kataloğu `ORDER_PAYMENT_COMPLETED` dahil olmak üzere bazı alanlarda backend ve UI metinleri arasında uyumsuzluk sinyali var.
- Abonelik yönetimi var ama event katalogu/tenant-level seed/yaşam döngüsü daha sağlam bir yönetim gerektiriyor.

Etki dosyaları:
- `app/Services/NotificationTemplateService.php`
- `app/Http/Controllers/Api/NotificationSubscriptionController.php`
- `resources/js/views/settings/SettingsView.vue`

### E. Ürün/Import/Action Entegrasyon Tutarlılığı
- Mapping eşleşmesi ve otomatik aksiyon çözümü var ancak bazı mapping türlerinde import hattı tam kapatma akışları sınırlı.
- Import commit aşamasında duplicate ve doğrulama hatalarına karşı eylem çözümü var; fakat yeniden deneme + re-validate zinciri tipik operasyonel yükü karşılamada yetersiz.

Etki dosyaları:
- `app/Http/Controllers/Api/OrderController.php`
- `app/Services/RequiredActionWorkflowService.php`
- `app/Services/RequiredActionResolver.php`

### F. Ödeme Entegrasyonu (Stripe)
- Stripe temel seviyede çalışır:
  - payment intent oluşturma/confirm
  - webhook durumları
- Production açısından idempotent güvenlik, retry stratejisi ve ödeme başarısızlığı sonrası operasyonel izleme daha geniş hale getirilmeli.

Etki dosyaları:
- `app/Services/StripePaymentService.php`
- `app/Http/Controllers/Api/PaymentController.php`

## 3) Eksiklerin Sıralaması (Öncelik)

- **P0 – Import UX/Backend Teklifi**
  - hardcoded sample kaldır, sampleı backend ile serve et.
  - XLSX intake ekle veya planında açıkça kapat.
  - Import validation + required action bridge’i tip bazlı tamamla.

- **P1 – Required Action Kapama Mantığı**
  - `invalid_artwork`, `duplicate_order`, `product_unavailable` için çözüm ve yeniden doğrulama.
  - `reopen`/`escalate`/`resolve` akışlarını bütün türlere yay.

- **P1 – Rol ve Güvenlik Tutarlılığı**
  - owner rol yönetimi UI’da birebir backend ile eşle.
  - kritik eylemler için izin kısıtlarını tek davranışta denetle.

- **P2 – Bildirim Yönetimi**
  - Event kataloğunu tenant seviyede sabit/seed kontrollü yönet.
  - log ve retry metriklerini operasyon ekranında netleştir.

- **P2 – Ödeme Akışı Dayanıklılığı**
  - hata senaryolarını genişlet (failed/canceled/requires_action).
  - order lifecycle ile güçlü eşleşme.

## 4) Modül Bazlı Uygulama Önerisi (Sıralı)

1. Import modülünü kapat:
   - örnek/veri kaynağı: backend.
   - parser/mapped row modeli standardize et.
   - preview/commit akışları için required action yeniden tetikleme.
2. Required Action modülünü kapat:
   - tüm türler için çözüm + otomatik revalidation.
3. Settings rol yönetimini kapat:
   - owner rolü UI’da görünür + güvenli geçiş.
4. Notifications:
   - event kataloğu ve seed kontrolü tek noktaya taşı.
5. Stripe:
   - ödeme akışı senaryolarını e2e ile doğrula, retry ve status audit’i kalıcılaştır.

## 5) Sonuç

Projede temel omurga ve birçok modül çalışır durumda.  
Ancak örnek site ile “birebir uyum” hedefleniyorsa, yukarıdaki **P0/P1 maddeleri** önce uygulanmalı; bu maddeler olmadan modül bitimi kabul edilmemelidir.
