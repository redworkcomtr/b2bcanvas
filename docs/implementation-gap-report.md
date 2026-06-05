# B2B Canvas - Eksiklik Raporu (güncel: 2026-05-11)

## 1) Özet

Bu rapor, ilk olarak 2026-05-09 tarihinde mevcut `main` branch üzerinde yapılan hızlı denetimin çıktılarını içeriyordu. 2026-05-11 güncellemesiyle PostgreSQL yerel çalışma düzeni, XLSX import, backend template, required action kapama akışları, owner rol görünürlüğü ve Stitch tabanlı admin panel görsel dili büyük ölçüde uygulandı.

Bu doküman artık kalan işleri ayırır: tamamlanan P0/P1 bulgular, hâlâ üretim sertleştirmesi isteyen alanlar ve sonraki sprint adayları.

## 2) Modül Bazında Eksik Durumlar

### A. Auth / Tenant / Permission
- Durum: Owner rolü artık ayarlar UI’ında görünür; owner kullanıcı `owner`, `admin`, `operations`, `support`, `viewer` rollerini atayabilir.
- Davet, rol güncelleme ve pasifleştirme akışı test kapsamına alınmış durumda.
- Kalan: Tenant ayarları paneli (`support_email`, default shipping service, tenant settings JSON) ve rol bazlı UI/E2E test kapsamı genişletilmeli.

Etki dosyaları:
- `app/Models/User.php`
- `resources/js/views/settings/SettingsView.vue`

### B. Import Orders (CSV/XLSX Intake + Import Motoru)
- Durum: Örnek import template’i backend’den servis ediliyor; frontend hardcoded sample kaldırıldı.
- Durum: XLSX parser eklendi ve backend preview akışında kullanılıyor.
- Durum: Import commit işlemi için `ProcessImportRowsJob` eklendi; duplicate ve unavailable product hataları required action’a bağlanıyor.
- Kalan: Commit akışı production’da gerçek async queue dispatch ile çalıştırılmalı; büyük dosyalar için chunk/resume stratejisi ayrıca sertleştirilmeli.

Etki dosyaları:
- `resources/js/views/orders/ImportOrdersView.vue`
- `app/Services/CsvOrderImportParser.php`
- `app/Http/Controllers/Api/OrderController.php` (`importPreview`, `commitImport`)

### C. Required Actions / Çözümler
- Durum: `product_mapping_required`, `address_error`, `invalid_artwork`, `duplicate_order`, `product_unavailable` için çözümleme akışları backend ve UI tarafında genişletildi.
- Durum: Mapping oluşturulduğunda ilgili import row/order item yeniden doğrulama zinciri test kapsamına alındı.
- Kalan: Media kalite validasyonu (`invalid_artwork` kaynağı), gerçek üretim öncesi dosya kontrolü ve daha zengin action timeline UX’i Sprint 7/Sprint 4 kapsamına kalıyor.

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
- Durum: Mapping eşleşmesi, duplicate order çözümü ve unavailable product alternatifi import hattına bağlandı.
- Kalan: Yüksek hacimli importlarda retry/revalidate zinciri gerçek queue worker ve operasyon loglarıyla daha görünür hale getirilmeli.

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

- **Tamamlandı – Import UX/Backend P0**
  - backend sample template, XLSX preview, import job sınıfı, duplicate/unavailable action bridge’i.

- **Tamamlandı – Required Action Kapama P1**
  - `invalid_artwork`, `duplicate_order`, `product_unavailable` çözüm formları ve backend işleme.
  - `reopen`/`escalate`/`resolve` akışları required action türlerine yayıldı.

- **Tamamlandı – Rol ve Güvenlik Tutarlılığı P1**
  - owner rol yönetimi UI’da backend ile eşlendi.
  - kritik kullanıcı ve katalog işlemleri feature testlerle korunuyor.

- **Sıradaki P1/P2 – Tenant Settings + Bildirim Yönetimi**
  - Tenant settings paneli ekle.
  - Event kataloğunu tenant seviyede sabit/seed kontrollü yönet.
  - log ve retry metriklerini operasyon ekranında netleştir.

- **P2 – Ödeme Akışı Dayanıklılığı**
  - hata senaryolarını genişlet (failed/canceled/requires_action).
  - order lifecycle ile güçlü eşleşme.

## 4) Modül Bazlı Uygulama Önerisi (Sıralı)

1. UI/UX final temizlik:
   - Issues, Product Catalog, Settings, Order Wizard ve mobile kırılımlarında Stitch dilini tamamla.
2. Tenant Settings:
   - tenant identity/settings paneli, policy ve testler.
3. Notifications:
   - event kataloğu ve seed kontrolü tek noktaya taşı.
4. Stripe:
   - ödeme akışı senaryolarını e2e ile doğrula, retry ve status audit’i kalıcılaştır.
5. Production hardening:
   - gerçek async queue, runbook, webhook/API token modülleri.

## 5) Sonuç

Projede temel omurga ve birçok modül çalışır durumda.  
Ancak örnek site ile “birebir uyum” hedefleniyorsa, yukarıdaki **P0/P1 maddeleri** önce uygulanmalı; bu maddeler olmadan modül bitimi kabul edilmemelidir.
