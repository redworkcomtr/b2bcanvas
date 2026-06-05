# B2B Canvas — Laravel 13 + Vue 3 Geliştirme Planı

> Bu doküman, `b2bcanvas` deposunun mevcut Laravel + Vue altyapısı üzerinde, incelenen referans B2B baskı portalının birebir fonksiyonel eşdeğerini geliştirme planını içerir. Repo zaten temel modüllerle ayağa kalkmış durumda; plan, mevcut durumu **özetler**, eksik kısımları **listeler** ve **fazlar halinde tamamlama yol haritası** sunar.

---

## 0. Mevcut Repo Durumu (Snapshot — 2026-05-10)

Repo kökünde halihazırda çalışan bir Laravel 13 + Vue 3 + TypeScript SPA monolithi var. Mevcut sayım:

- **27 Eloquent modeli** (`app/Models/`): Tenant, User, UserInvite, Order, OrderItem, OrderStatusEvent, ProductType, ProductVariant, ProductOption, ProductMapping, MappingRule, Issue, IssueComment, RequiredAction, RequiredActionComment, ClaimResolution, Payment, Import, ImportRow, MediaFile, NotificationSubscription, NotificationMailLog, SavedView, AuditLog (+ Concerns trait klasörü).
- **11 servis sınıfı** (`app/Services/`): OrderStatusService, ProductPricingService, ProductMappingEngine, MappingRuleMatcher, CsvOrderImportParser, IssueWorkflowService, ClaimWorkflowService, RequiredActionWorkflowService, RequiredActionResolver, NotificationTemplateService, StripePaymentService.
- **13 API controller** ve karşılık gelen rotalar (`routes/web.php` içinde `/api` prefix'i altında): Auth, Portal, Order, ProductCatalog, ProductMapping, Issue, Claim, RequiredAction, Notification, NotificationSubscription, Payment, MediaUpload, User.
- **10+ migration**: temel B2B şeması, auth sertleştirme (user_invites, active flag), media files, orders core (tracking, status_events, saved_views), required_action ve ticket workflow alanları, claim_resolutions, notification mail logs, payments.
- **Vue SPA**: `resources/js/` altında App.vue, app.ts, router, stores (Pinia), components, views (orders, issues, products, settings), types, lib. UI primitive'leri `resources/js/components/ui` altında lokal shadcn-vue (new-york stil, neutral baseColor, CSS variables) olarak duruyor.
- **Tooling**: PHPUnit 12 + Pint + Pail (Laravel logs) + Tinker. JS tarafında Vitest 4 + @vue/test-utils. Vite 8, Tailwind 4, TypeScript. Yerel geliştirme PHP 8.3 + PostgreSQL 16 Homebrew servisleriyle çalışır.
- **`docs/`**: api-contract.md, implementation-gap-report.md, implementation-notes.md, production-readiness.md — mevcut sürümün eksiklik raporu da burada (özellikle Import, Required Actions, Owner Role yönetimi, Stripe dayanıklılık alanları işaretlenmiş).

Bu plan, sıfırdan kurulum yerine **bu mevcut iskelet üzerinde** kalan boşlukları kapatmaya odaklanır.

---

## 1. Hedef Sistemin Özeti

Referans portal, bir baskı (canvas / framed print / push-pin) üreticisi ile B2B müşterileri arasındaki sipariş akışını yöneten self-servis bir paneldir. İş akışının özü:

1. Müşteri portala sipariş gönderir (tekil sipariş formu, CSV toplu yükleme veya API entegrasyonu).
2. Sistem siparişi doğrular; müşterinin SKU/ad alanı üreticinin katalogundaki bir varyantla eşleşmiyorsa **product_mapping_required** tipinde bir `required_action` açılır, sipariş `action_needed` durumuna düşer.
3. Müşteri **ProductMapping + MappingRule** kurarak SKU/name/fulfillment_sku → ürün varyantı eşlemesi yapar; sipariş tekrar doğrulanır → `verified`.
4. Gerekirse ödeme alınır (Stripe Payment Intent) → `submitted` → `in_production`.
5. Üretim akışı: `in_production` → `shipped` → `closed` (veya herhangi bir noktadan `cancelled`).
6. Her aşamada **Issue** açılabilir (Support Ticket / Claim / Required Action) — yorumlanır, eklentilerle desteklenir, çözüldükçe `resolved`/`closed`'a alınır.
7. Bildirimler (`NotificationSubscription`) ile durum değişimleri e-posta olarak iletilir; `NotificationMailLog` denetlenir.

Bu özet **mevcut migration ve api-contract'ta** dokümante edilmiş haldedir; aşağıdaki bölümler onu detaylandırır.

---

## 2. Teknoloji Yığını (Sabit)

| Katman | Seçim | Notlar |
|---|---|---|
| Dil | PHP 8.3, TypeScript 5 | composer.json + tsconfig.json |
| Backend Framework | Laravel 13.7 | route grouping, model events, queue, notifications, mail, storage |
| Frontend Framework | Vue 3.5 (Composition API) | `<script setup>` + TypeScript |
| State Management | Pinia 3 | feature-bazlı store'lar `resources/js/stores/` |
| Router | Vue Router 4 | history mode, guard'lar auth/tenant için |
| Build | Vite 8 + laravel-vite-plugin | HMR + manifest, prod build |
| Styling | Tailwind 4 (@tailwindcss/vite) | utility-first, dark mode opsiyonu için `class` stratejisi |
| UI Kit | shadcn-vue (new-york style) | `components.json` — lokal kopyalanır, NPM paketi değildir |
| Icons | lucide-vue-next | tree-shaken SVG iconlar |
| Auth | Laravel Sanctum (SPA mode) | cookie-based session, CSRF, `auth:sanctum` middleware |
| ORM | Eloquent | mevcut modeller |
| DB | PostgreSQL 16 | Yerel geliştirme Homebrew servisi, production managed PostgreSQL |
| Cache / Queue / Session | Redis (prod) | Laravel Queue worker + Pail logs |
| Realtime | Laravel Reverb (WebSocket) | issue thread'leri, sipariş status değişimi için broadcast |
| Mail | Symfony Mailer + SES / Postmark / Resend | `NotificationTemplateService` üzerinden |
| File Storage | S3 Filesystem driver (prod) / `local` (dev) | `MediaFile` modeli ile metadata |
| Ödeme | Stripe (`StripePaymentService`) | Payment Intent + webhook |
| CSV/Excel | League CSV (mevcut `CsvOrderImportParser`) + PhpSpreadsheet (xlsx için ekleme önerilir) | |
| Test | PHPUnit 12 (backend), Vitest 4 + Vue Test Utils (frontend), Playwright (E2E — eklenecek) | |
| CI/CD | GitHub Actions (`.github/` mevcut) | pint + phpstan + tests + npm test + build |
| Lint / Format | Laravel Pint (PHP), ESLint + Prettier (TS/Vue) | |
| Runtime | Yerel servisler + Laravel serve/Vite | Production'da nginx + php-fpm + managed servisler |

---

## 3. Çok Kiracılı (Multi-Tenant) Yapı

Repo'da **single-database + tenant_id sütunlu** yaklaşım kullanılıyor. Mevcut migration'da `tenants` tablosu var; `users.tenant_id` foreign key; tüm domain tabloları (`orders`, `product_mappings`, `issues`, `required_actions`, `imports`, `audit_logs`, vb.) `tenant_id` ile bağlanıyor. Middleware `EnsureActiveTenant` rota gruplarına uygulanmış.

### 3.1 Eklenmesi Önerilen Konseptler
- **Global Scope**: `app/Models/Concerns/BelongsToTenant.php` trait'i (varsa) tüm tenant'lı modeller için `addGlobalScope` ekleyip cross-tenant data leak'i derleme zamanında değil model seviyesinde önler.
- **Tenant Context Resolver**: `App\Support\TenantContext` singleton — `auth()->user()->tenant_id`'yi alıp request scope'unda tutar; Job'lar ve scheduled task'lar için manuel set edilebilir.
- **Tenant Policy Helper**: Tüm Policy sınıfları `tenant_id` kontrolünü tek bir `BasePolicy::sameTenant($user, $model)` ile yapsın.

### 3.2 Roller
`users.role` enum: `owner`, `admin`, `operator`, `viewer` (gap-report owner için UI eksiği işaretlemiş). Önerilen RBAC matrisi:

| Yetki | owner | admin | operator | viewer |
|---|:---:|:---:|:---:|:---:|
| Tenant ayarlarını yönetme | ✅ | ❌ | ❌ | ❌ |
| Kullanıcı davet/rol değiştirme | ✅ | ✅ | ❌ | ❌ |
| Sipariş oluşturma & iptal | ✅ | ✅ | ✅ | ❌ |
| Mapping CRUD | ✅ | ✅ | ✅ | ❌ |
| Ticket/Claim açma | ✅ | ✅ | ✅ | ❌ |
| Sipariş ve Issue görüntüleme | ✅ | ✅ | ✅ | ✅ |
| Bildirim aboneliği yönetme | ✅ (tüm tenant) | ✅ (kendisi) | ✅ (kendisi) | ✅ (kendisi) |

Policy sınıfları zaten `app/Policies/` altında — eksik kısım Owner görünürlüğünün UI tarafında tamamlanması (`SettingsView.vue`).

---

## 4. Veri Modeli (Mevcut Şema Üzerinden)

### 4.1 Mevcut Tablolar ve İlişkileri

```
tenants ─── users (n) ─── notification_subscriptions (n)
   │           │
   │           └─── audit_logs (n)
   │           └─── user_invites (n)
   │
   ├─── product_types (n) ─── product_variants (n)
   │                       └─ product_options (n)
   │
   ├─── product_mappings (n) ─── mapping_rules (n)
   │           └─ product_variants ↑ (FK)
   │
   ├─── orders (n) ─── order_items (n)
   │           ├─ order_status_events (n)
   │           └─ payments (n)
   │
   ├─── imports (n) ─── import_rows (n)
   │
   ├─── issues (n) ─── issue_comments (n)
   │
   ├─── required_actions (n) ─── required_action_comments (n)
   │
   ├─── saved_views (n)
   ├─── media_files (n)
   └─── notification_mail_logs (n)
```

### 4.2 Önemli Sütun Notları

**`orders`**
- `uuid` (public detay URL'i için), `order_number` (kısa görünür no, unique)
- `status` enum: `draft`, `validation_failed`, `action_needed`, `verified`, `submitted`, `in_production`, `shipped`, `closed`, `cancelled`
- `payment_status` enum: `not_required`, `pending`, `paid`, `failed`, `refunded`
- `shipping_address` ve `totals` JSON sütunları (Eloquent cast: `array`)
- `tracking_number`, `tracking_url` (kargo)

**`order_items`**
- `product_variant_id` nullable — mapping yapılmadıysa null
- `design_images`, `print_images`, `options` JSON
- `panel_summary` string (örn. "3 paneller: 16x32, 16x32, 16x32")

**`mapping_rules`**
- `field` enum: `sku`, `name`, `fulfillment_sku`
- `operator` enum: `equals`, `contains`, `starts_with`, `regex` (referans sistemden geniş!)
- `priority` int — birden fazla kural aynı satıra uyarsa düşük priority kazanır

**`issues`**
- `type` enum (gözlemlenen): `ticket`, `claim` (`required_action`'lar ayrı tablo)
- `request_type` (örn. `address_change`, `damaged_on_delivery`, `general`)
- `status` enum: `open`, `in_progress`, `waiting_customer`, `resolved`, `closed`
- `reasons` JSON (multi-select reason kodları)
- `total_notes_count`, `unread_notes_count` denormalize sayaçlar

**`required_actions`**
- `type` enum: `product_mapping_required`, `address_error`, `invalid_artwork`, `duplicate_order`, `product_unavailable` (gap-report'a göre son 3'ünün çözüm akışı henüz tamamlanmamış)
- `payload` JSON (action-specific data)

**`payments`**
- Stripe payment intent ID, amount_cents, currency, status, raw payload

**`notification_subscriptions`**
- `event` enum (gözlemlenen): `ORDER_SHIPPED`, `ORDER_ACTION_NEEDED`, `ORDER_ISSUE_COMMENT_ADDED`, `ORDER_VALIDATION_FAILED`, `ORDER_PAYMENT_COMPLETED`
- `unsubscribe_token` (eklenen migration ile, link-based opt-out için)

### 4.3 Eksik / Eklenmesi Önerilen Tablolar
- **`webhook_endpoints`** — müşterinin kendi sistemine giden outbound webhook'lar için (URL, secret, events[], retries, last_status)
- **`api_tokens`** — Sanctum'un `personal_access_tokens` tablosu mevcut ama UI'da self-service token yönetim ekranı yok
- **`order_drafts`** — yeni sipariş wizard'ında auto-save için (mevcutta `orders.status=draft` üzerinden gidiliyor, ayrı tablo opsiyonel)
- **`product_variant_option_compatibility`** — hangi opsiyonun hangi varyantla uyumlu olduğunu tutmak için (şu an `product_options.product_type_id` ile dolaylı; daha granüler kontrol gerekirse)

---

## 5. API Sözleşmesi (Mevcut + Tamamlanacaklar)

Tüm endpoint'ler `/api` prefix'i altında, `auth:sanctum` + `EnsureActiveTenant` middleware'leri ile korunur. Rate limit `throttle:120,1` (auth login için `10,1`).

### 5.1 Var Olan (routes/web.php'den)

**Auth**
- `POST /api/auth/login`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /api/auth/session`
- `POST /api/auth/logout`

**Portal Bootstrap**
- `GET /api/portal` — single round-trip ile tüm dashboard verisini döner (tenant, user, metrics, recent orders, product types, mappings, issues, required actions, subscriptions)
- `GET /api/workspace` — aynı (alias)

**Orders**
- `GET /api/orders` (filter+sort+paginate)
- `GET /api/orders/export` (CSV/Excel indirme)
- `GET /api/orders/saved-views`, `POST .../saved-views`, `DELETE .../saved-views/{savedView}`
- `GET /api/orders/imports`, `GET /api/orders/imports/template`
- `POST /api/orders/imports/preview` — CSV upload + parse + dry-run
- `POST /api/orders/imports/{import}/commit`
- `GET /api/orders/imports/{import}/errors`
- `POST /api/orders` — tek sipariş oluşturma
- `GET /api/orders/{uuid}`
- `PATCH /api/orders/{uuid}/address`
- `PATCH /api/orders/{uuid}/notes`
- `POST /api/orders/{uuid}/transition` — status değiştirme
- `POST /api/orders/{uuid}/payment/intent`, `POST .../payment/confirm`

**Product Catalog**
- `GET /api/products`
- `POST /api/products/types`, `PATCH .../types/{productType}`, `DELETE ...`
- `POST /api/products/types/{productType}/variants`, `PATCH /api/products/variants/{variant}`, `DELETE ...`
- `POST /api/products/types/{productType}/options`, `PATCH /api/products/options/{option}`, `DELETE ...`

**Product Mappings**
- `GET /api/product-mappings`
- `POST /api/product-mappings`
- `POST /api/product-mappings/simulate` — "bu kural mevcut item'larıma uyar mı?" preview

(Issue / Claim / Required Action / Notification / Payment / Media / User endpoint'leri için `routes/web.php` dosyasının tamamı referans alınmalı.)

### 5.2 Eklenmesi Önerilen Endpoint'ler

- `PUT /api/product-mappings/{mapping}` — şu an sadece POST var; düzenleme gerekiyor
- `DELETE /api/product-mappings/{mapping}`
- `POST /api/product-mappings/bulk-import` — CSV ile toplu mapping yükleme
- `POST /api/orders/{uuid}/items/{item}/replace-mapping` — manuel item-level mapping override
- `POST /api/orders/bulk-cancel`, `POST /api/orders/bulk-tag`
- `POST /api/orders/{uuid}/items/{item}/designs` — ek tasarım dosyası yükleme
- `GET /api/webhooks/endpoints`, `POST /api/webhooks/endpoints`, `DELETE ...` — outbound webhook yönetimi
- `GET /api/api-tokens`, `POST /api/api-tokens`, `DELETE ...` — self-service API token UI
- `POST /api/webhooks/stripe` — public webhook (CSRF muaf)
- `POST /api/webhooks/shipping/{carrier}` — kargo tracking güncellemeleri
- `GET /api/reports/orders-summary?from=&to=` — aylık özet
- `GET /api/notifications/in-app` — in-app bildirim feed'i (eklenecek modül)

### 5.3 Yanıt Şekli Standardı
Mevcut controller'lar JSON resource pattern kullanıyor olabilir; tutarlılık için:

```json
{
  "data": { ... } veya [ ... ],
  "meta": { "page": 1, "page_size": 25, "total": 247, "total_pages": 10 },
  "links": { "self": "...", "next": "...", "prev": null }
}
```

Hata yanıtı (Laravel default'una sadık kalır):
```json
{ "message": "...", "errors": { "field": ["..."] } }
```

---

## 6. Vue SPA Yapısı

### 6.1 Mevcut Dizin (resources/js/)
```
App.vue
app.ts                  → bootstrap, Pinia + Router register
router/                 → vue-router config
stores/                 → Pinia store'ları (auth, portal, orders, ...)
components/             → genel bileşenler + ui/ (shadcn-vue)
lib/                    → utils, http client (axios/fetch)
types/                  → TypeScript tip tanımları (API response shapes)
views/
  orders/
  issues/
  products/
  settings/
```

> Repoda ayrıca üst-seviye `src/` klasörü de var (boş alt klasörler: api, assets, components, layouts, router, stores, types, views). Bu, eskiden ayrı bir Vue 3 projesi olarak başlatılıp resources/js'e taşınmış olabilir. **Karışıklık yaratmaması için `src/` ya silinmeli ya da bir README ile arşivlendiği belirtilmelidir.**

### 6.2 Önerilen Tam Dizin Yapısı

```
resources/js/
├── app.ts                          # Pinia + Router + axios bootstrap
├── App.vue                          # root layout switch
├── router/
│   ├── index.ts                    # route definitions + guards
│   ├── guards.ts                   # requireAuth, requireRole, ensureTenant
│   └── routes/
│       ├── orders.ts
│       ├── products.ts
│       ├── issues.ts
│       └── settings.ts
├── stores/
│   ├── auth.ts                     # login, session, logout
│   ├── portal.ts                   # /api/portal bootstrap cache
│   ├── orders.ts                   # list, detail, filters, savedViews
│   ├── orderDraft.ts               # New Order wizard state (auto-save)
│   ├── products.ts                 # catalog (admin/producer)
│   ├── mappings.ts
│   ├── issues.ts
│   ├── requiredActions.ts
│   ├── notifications.ts            # subscriptions + in-app feed
│   └── ui.ts                       # toasts, modals, sidebar
├── lib/
│   ├── api.ts                      # axios instance, interceptors (csrf, 401 retry)
│   ├── utils.ts                    # cn(), formatDate, currency, ...
│   ├── filters.ts                  # URL query string serialization
│   └── upload.ts                   # presigned upload helper
├── types/
│   ├── api.ts                      # generated/hand-written DTO types
│   ├── domain.ts                   # Order, Issue, etc.
│   └── forms.ts
├── components/
│   ├── ui/                         # shadcn-vue (Button, Input, Dialog, ...)
│   ├── layout/
│   │   ├── AppShell.vue            # sidebar + topbar + outlet
│   │   ├── Sidebar.vue
│   │   ├── Topbar.vue              # search, notifications bell, profile menu
│   │   └── Breadcrumbs.vue
│   ├── orders/
│   │   ├── OrdersTable.vue
│   │   ├── OrderStatusBadge.vue
│   │   ├── OrderStatusTimeline.vue
│   │   ├── OrderFilterBar.vue
│   │   ├── ShippingAddressEditor.vue
│   │   └── ItemPanelGallery.vue
│   ├── orderWizard/
│   │   ├── WizardShell.vue
│   │   ├── Step1ProductSelect.vue
│   │   ├── Step2Configure.vue
│   │   ├── Step3Extras.vue
│   │   ├── Step4Shipping.vue
│   │   └── Step5Summary.vue
│   ├── mappings/
│   │   ├── MappingTable.vue
│   │   ├── MappingDialog.vue
│   │   ├── RuleBuilder.vue
│   │   └── ConfigurationEditor.vue
│   ├── issues/
│   │   ├── IssueTable.vue
│   │   ├── IssueDetail.vue
│   │   ├── IssueCommentThread.vue
│   │   └── OpenTicketDialog.vue
│   └── shared/
│       ├── DataTable.vue           # generic, used by all list pages
│       ├── EmptyState.vue
│       ├── ConfirmDialog.vue
│       └── FileDropzone.vue
├── views/
│   ├── auth/
│   │   ├── LoginView.vue
│   │   └── PasswordResetView.vue
│   ├── DashboardView.vue
│   ├── orders/
│   │   ├── OrdersListView.vue
│   │   ├── OrderDetailView.vue
│   │   ├── NewOrderView.vue        # wraps WizardShell
│   │   └── ImportOrdersView.vue
│   ├── products/
│   │   └── MappingsView.vue
│   ├── issues/
│   │   ├── TicketsListView.vue
│   │   ├── ClaimsListView.vue
│   │   ├── RequiredActionsListView.vue
│   │   └── IssueDetailView.vue
│   └── settings/
│       ├── SettingsView.vue
│       ├── ProfilePanel.vue
│       ├── TenantPanel.vue
│       ├── UsersPanel.vue
│       ├── NotificationsPanel.vue
│       ├── ApiTokensPanel.vue
│       └── WebhooksPanel.vue
└── styles/
    └── app.css                     # @import "tailwindcss" + theme variables
```

### 6.3 Router Stratejisi
- `/auth/login`, `/auth/forgot-password`, `/auth/reset-password/:token` → public
- `/` → DashboardView (auth + tenant required)
- `/orders`, `/orders/new`, `/orders/import`, `/orders/:uuid` → orders
- `/products/mappings` → mappings
- `/issues/tickets`, `/issues/tickets/:id`, `/issues/claims`, `/issues/claims/:id`, `/issues/actions`, `/issues/actions/:id` → issues
- `/settings` → tabbed settings (profile, tenant, users, notifications, api-tokens, webhooks)
- `meta: { requiresAuth: true, requiredRole: [...] }` ile route guard'lar

### 6.4 State Pattern
- Her store `defineStore('orders', () => { ... })` setup syntax
- API çağrıları doğrudan store içinden axios ile değil, `lib/api.ts` üzerinden — interceptor'lar tek noktada
- Mutations için "optimistic update" (örn. status değişimi anında UI'da, hata olursa rollback)
- `portal.ts` store'u uygulama açılışında `/api/portal`'ı çağırır ve diğer store'ları seed eder; bu sayede sayfa-içi navigasyonda flicker olmaz.

---

## 7. Servisler ve İş Mantığı (Mevcut + Tamamlanacak)

### 7.1 Var Olanlar (`app/Services/`)
- **OrderStatusService** — durum geçiş kuralları ve `OrderStatusEvent` log'u
- **ProductPricingService** — variant + options'a göre toplam hesaplama
- **ProductMappingEngine** — order item'larına uygun mapping aramak
- **MappingRuleMatcher** — `field/operator/value` ile eşleşme kontrolü (equals, contains, starts_with, regex)
- **CsvOrderImportParser** — CSV → ImportRow array
- **IssueWorkflowService**, **ClaimWorkflowService** — durum geçişleri ve yan etkiler
- **RequiredActionWorkflowService**, **RequiredActionResolver** — aksiyon açma/çözme/otomatik kapatma
- **NotificationTemplateService** — event → e-posta template + payload
- **StripePaymentService** — Payment Intent + confirm + webhook

### 7.2 Tamamlanması / Eklenmesi Önerilen Servisler

- **`ImportPipeline`** — ImportRow'ları queue üzerinde job'lara çevirip parça parça işleyen orchestrator. Şu an `commitImport` muhtemelen senkron çalışıyor; 1000+ satır için job'a alınmalı.
- **`XlsxImportParser`** — gap-report'ta işaretlenmiş; PhpSpreadsheet ile CSV parser'a paralel xlsx desteği.
- **`OutboundWebhookDispatcher`** — durum değişimlerinde müşterinin webhook endpoint'ine HMAC imzalı POST atan job.
- **`MediaIntakeService`** — yüklenen panel image'lerini doğrulayan (DPI, color mode, bleed) servis; uygunsuzları reddeder/uyarır.
- **`PreviewRenderer`** — yüklenen design'i ürün önizleme görselinin üzerine kompozit eden işlem (Intervention Image veya Imagick).
- **`ReportingService`** — saved view + tarih aralığı + filtre kombinasyonlarından CSV/Excel rapor üretir.
- **`AuditLogger`** — kritik mutation'ları (cancel, address change, mapping create/delete) `audit_logs`'a yazan facade.

### 7.3 Event/Listener İskelesi
Laravel Events + Queued Listeners pattern'i:

```
OrderStatusChanged       → SendNotificationEmails, FireOutboundWebhook, AppendStatusEvent
OrderImported            → AutoResolveDuplicateActions, ValidateMappings
MappingCreated           → ReprocessFailedItems
IssueCreated             → NotifyAssignee, RecomputeOrderHealth
RequiredActionResolved   → CheckIfOrderUnblocked
PaymentSucceeded         → TransitionOrderToSubmitted
PaymentFailed            → CreateRequiredAction('payment_failed')
```

`app/Events/` zaten var (mevcut migration tarihi sonrası `Events` klasörü oluşturulmuş). Listener mapping `EventServiceProvider`'da merkezi tutulmalı.

---

## 8. Auth ve Güvenlik

### 8.1 Auth Akışı (Sanctum SPA)
- Vue uygulaması `/api/auth/login` POST eder → Sanctum cookie döner
- Sonraki tüm istekler aynı domain'de cookie ile yetkilenir (XSRF token header'ı + CSRF cookie)
- Refresh için ek endpoint gerekmiyor (cookie session lifetime üzerinden)
- Production: HttpOnly + Secure + SameSite=Lax cookie

### 8.2 Sertleştirme Kontrol Listesi
- `app/Http/Middleware/EnsureActiveTenant` — kullanıcı `tenant_id` null ve role != producer ise 403
- Rate limit: `auth/login` (10/min), `forgot-password` (5/min), genel (120/min) — mevcut
- Brute-force koruma: başarısız login → throttle + IP-based ek throttle (Laravel default + spesifik)
- Şifre politikası: `Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()` (Laravel built-in HIBP API ile)
- 2FA (TOTP): `pragmarx/google2fa` veya `laravel/fortify` two-factor modülü — ileri sprint
- Audit log: her login/logout, role değişimi, tenant ayar değişimi
- File upload doğrulaması: `MediaIntakeService` + `Storage::disk('s3')->putFileAs` (asla `move_uploaded_file` direkt)
- Tenant isolation testi: PHPUnit feature test'inde "X tenant'ı Y tenant'ının order'ını göremez" senaryosu
- CSP / HSTS / Permissions-Policy header'ları: `app/Http/Middleware/SecurityHeaders`
- Stripe webhook signature: `Stripe\Webhook::constructEvent` ile imza doğrulama (mevcut `StripePaymentService`'te olmalı, gap-report idempotency için iyileştirme istiyor)

---

## 9. Asenkron İş Mantığı (Queue)

Önerilen kuyruklar:

| Queue adı | İçerik | Worker sayısı (prod) |
|---|---|---|
| `default` | genel notification, mail | 2 |
| `imports` | CSV/XLSX commit, ImportRow batch işleme | 1-2, timeout=0 |
| `media` | image validation, preview render | 2 |
| `webhooks` | outbound webhook dispatch + retry | 2 |
| `notifications` | e-posta gönderimi (Resend/SES API çağrısı) | 2-4 |
| `audit` | düşük öncelikli audit log batching | 1 |

`composer.json` `dev` script'inde zaten `php artisan queue:listen --tries=1` çalışıyor; prod için Supervisor + Horizon eklenmesi öneriliyor (`laravel/horizon` paketi).

---

## 10. Realtime Bildirimler

Önerilen:
- **Laravel Reverb** (kendi WebSocket sunucusu) → ayrı yerel/production servis olarak çalıştırılır.
- Vue tarafında `laravel-echo` + `pusher-js` (Reverb pusher protokolüyle uyumlu).
- Kanallar:
  - `private-tenant.{tenantId}` → tenant-wide event'ler (yeni required action, sipariş status)
  - `private-tenant.{tenantId}.issue.{issueId}` → issue thread'ine yorum geldiğinde
  - `private-user.{userId}` → kullanıcıya özel bildirimler (in-app notification feed)
- Backend: `ShouldBroadcast` interface + queued broadcaster.

---

## 11. Dosya / Görsel Yönetimi

### 11.1 Mevcut
`media_files` tablosu + `MediaFile` modeli var. `MediaUploadController` rotaları mevcut.

### 11.2 Akış Önerisi
1. Vue → `POST /api/media/upload-url` ile presigned URL ister (S3 driver).
2. Browser doğrudan S3'e PUT yapar.
3. Vue → `POST /api/media` ile metadata kaydeder (path, mime, size, association: order_item_id + panel_index + role[ready|raw|design|print]).
4. Job tetiklenir → `MediaIntakeService` DPI / color profile / bleed kontrolü yapar; uygunsuzsa `required_action` açar (`invalid_artwork`).

### 11.3 Önerilen Doğrulamalar (`MediaIntakeService`)
- Min 100 DPI (kullanıcı uyarısı), önerilen 150-300 DPI
- Color mode: tercihen CMYK; RGB ise warning
- Bleed alanı: panel boyutuna göre kenarlardan min 0.125" (3mm) içeri taşıma
- Çözünürlük × inch ≥ panel boyutu — hesaplı kontrol
- Maks dosya boyutu: 200 MB (large format için), default 50 MB

---

## 12. Yeni Sipariş Wizard'ı (Form Akışı)

Vue tarafında 5 adımlı wizard. Adımlar arası state Pinia `orderDraft.ts` store'da; localStorage'a auto-save (debounced 1 sn). Backend tarafında `orders` tablosunda `status='draft'` ile yarım kayıtlar tutulur — refresh sonrası devam edilebilir.

| Adım | İçerik | Validasyon |
|---|---|---|
| 1. Product Select | product_type combobox → product_variant combobox + ürün kartı (price async) + "Select" butonu, çoklu ürün eklenebilir | en az 1 variant seçili |
| 2. Configure | her variant için: quantity (≥1), product SKU (opsiyonel, müşteri ref'i), print options (Bleed Method radio), panel images (her panel için ready/raw upload), frame options (Float Mount swatch radio), Hanging/Cover/Packing/Pushpin Color (varyanta göre conditional) | zorunlu opsiyonlar seçili + her panel için en az design veya ready image |
| 3. Extras | Rush (bool), Gift wrap, Insert notes (text) | — |
| 4. Shipping | full_name, address1, address2, city, state, zip, country, residential (bool), notes, phone, email + shipping service seçimi | adres + servis zorunlu |
| 5. Summary | tüm bilgilerin özeti + totals breakdown + onay + (gerekirse Stripe Payment Element) | totals = ProductPricingService sonucu |

`POST /api/orders` çağrısı: yeni sipariş yaratır → backend `OrderStatusService->transition('draft' → 'verified')` (mapping success'i varsa) → response'ta `uuid` döner → Vue `/orders/:uuid`'ye yönlendirir.

---

## 13. CSV / Excel Import

### 13.1 Mevcut
- `CsvOrderImportParser` çalışıyor.
- Endpoint'ler: preview / commit / errors / template var.
- Gap report'a göre: sample dosya frontend'de hardcoded, XLSX desteği yok, queue'ye alınmadan senkron işleniyor.

### 13.2 Tamamlama Planı
1. `GET /api/orders/imports/template` zaten var — frontend hardcoded sample'ı bu endpoint'ten çekecek şekilde değiştir (`ImportOrdersView.vue`).
2. `XlsxImportParser` ekle (PhpSpreadsheet); `OrderController->importPreview` detect-extension yapsın.
3. `commitImport` → senkron çalışıyorsa `ProcessImportRowsJob`'a taşı; tek satırlık başarısızlık tüm import'u durdurmasın.
4. Hata raporu: failed rows için indirilebilir CSV (`GET /imports/{id}/errors`).
5. Bulk required action: aynı SKU'da N satır hata varsa, N tane action yerine **1 grup action** açılsın.

### 13.3 CSV Şeması (Dot-notation, mevcut)
Mevcut sample dosya başlıkları (referans portal ile uyumlu):
```
clientOrderId, rush, carrier, shippingCode, shippingLabelUrl, trackingNumber,
shipToCustomer.fullName, shipToCustomer.address1..address2..city..state..zip..country..residential..notes,
notes (order),
items[N].quantity, items[N].productCode, items[N].clientProductCode, items[N].name,
items[N].productPreview, items[N].bleedMethod, items[N].designImages[M],
items[N].options[K].type, items[N].options[K].subType, items[N].options[K].option
```
Parser dot-notation'ı `Illuminate\Support\Arr::set()` ile nested array'e dönüştürmeli.

---

## 14. Mapping Engine Detayı

Mevcut `ProductMappingEngine` ve `MappingRuleMatcher`:

- Bir `order_item` için tenant'ın tüm `ProductMapping`'leri `priority ASC` sırayla denenir.
- Her mapping için bağlı tüm `MappingRule`'lar **AND** mantığıyla değerlendirilir; hepsi true ise mapping kazanır.
- Operator desteği: `equals` (case-insensitive trim), `contains`, `starts_with`, `regex` (PHP `preg_match`).
- Kazanan mapping'in `product_variant_id`'si item'a atanır; `configuration` (JSON) item.options'a merge edilir.
- Hiç eşleşme yoksa `required_action(type=product_mapping_required, payload={item_id, suggested_field, suggested_value})` açılır → order `action_needed`.

### Önerilen İyileştirmeler
- **Test/Simulate** endpoint mevcut (`POST /product-mappings/simulate`) — UI'da "Bu kural mevcut item'larıma uyar mı?" preview'u tetiklesin.
- **Bulk import** — CSV'den (clientSku, productVariantSku, panelConfig) kayıtları toplu içe aktaran endpoint.
- **Dry-run reprocess** — yeni mapping yaratıldığında "şu kadar failed item'ı çözer" sayısı response'ta dönsün.
- **Audit**: her mapping create/update/delete `audit_logs`'a düşsün.

---

## 15. Issues / Tickets / Claims / Required Actions Birleşik Model

Mevcut yapı: `issues` (tickets + claims) + `required_actions` (sistemce açılan) — iki ayrı tablo. UI'da üç ayrı tab. Bu makul; ancak frontend'de **DataTable** generic bileşeni ile aynı şablon kullanılmalı.

### Statü Geçişleri (issues)
```
open ─→ in_progress ─→ waiting_customer ─→ resolved ─→ closed
                       └── (re-open) ←─────────┘
```

### Yorumlar
- `issue_comments` zaten var.
- Realtime: yeni comment → broadcast `private-tenant.{id}.issue.{issueId}`.
- Attachment: `attachments` JSON'da media_file_id dizisi tut, ayrı pivot tablo gerekmez (basit kalır).

### Required Action Çözüm Akışı
- `product_mapping_required` → mapping yaratıldığında `RequiredActionResolver` event listener'ı kapatır.
- `address_error` → adres düzeltildiğinde kapatılır.
- `invalid_artwork` (gap-report'a göre çözüm akışı eksik) → yeni design upload tetikleyici eklenecek.
- `duplicate_order` → kullanıcı "yine de devam" veya "iptal et" seçer.
- `product_unavailable` → alternatif variant öner + tek tık swap.

---

## 16. Ödeme (Stripe)

Mevcut: `StripePaymentService`, `PaymentController` (intent + confirm), `payments` tablosu, webhook ana hat.

### Kapatılması Gereken Boşluklar (gap-report'tan)
1. **Idempotency**: `Idempotency-Key` header'ı her POST'ta unique olmalı; aynı key ile retry idempotent dönüşmeli.
2. **Webhook retry**: Stripe webhook'tan gelen event id'leri `processed_webhook_events` tablosunda tutulup tekrar işlenmesi engellensin.
3. **Failed payment recovery**: 3-Konum: `payment_failed` event'i → `required_action(type=payment_failed)` aç → kullanıcıya yeniden deneme/farklı kart seçeneği sun.
4. **Refund akışı**: claim resolved + refund_required ise stripe refund tetiklensin; `claim_resolutions` tablosuna log.
5. **Webhook signature**: production'da `STRIPE_WEBHOOK_SECRET` zorunlu kontrol.
6. **PaymentIntent expiration**: 24 saat içinde confirm edilmemiş intent'lar otomatik temizlensin.

---

## 17. Bildirimler

### 17.1 Mevcut
- `notification_subscriptions` (user-level event subscription) + `unsubscribe_token` (link-based opt-out)
- `notification_mail_logs` — gönderilen mail'leri ve durumlarını loglar
- `NotificationTemplateService` — event → markdown template

### 17.2 Event Kataloğu (sabitlenecek)
- `ORDER_VALIDATION_FAILED`
- `ORDER_ACTION_NEEDED`
- `ORDER_SHIPPED`
- `ORDER_ISSUE_COMMENT_ADDED`
- `ORDER_PAYMENT_COMPLETED` (gap-report UI uyumsuzluğu işaretlenmiş — backend/UI metinleri tek bir `lang/en/notifications.php` üzerinden yönetilsin)

### 17.3 Eklenecekler
- **In-App Notification Feed**: yeni tablo `in_app_notifications` (user_id, payload, read_at) + topbar'da bell icon + Reverb broadcast.
- **Slack/Webhook**: tenant düzeyinde Slack webhook URL → event'ler oraya da düşsün.
- **Digest**: günlük/haftalık özet maili (Laravel Scheduler + `digest_subscriptions`).

---

## 18. Producer (Üretici) Paneli

Repo şu an müşteri (tenant) perspektifinden geliştirilmiş. Producer rolü için ayrı view'lar gerekecek:

- **/admin/orders** — tüm tenant'ların siparişleri tek listede + tenant filtresi.
- **/admin/production-board** — kanban: in_production / shipping_label_created / shipped sütunları, drag & drop ile aşama geçişi.
- **/admin/catalog** — ProductType / ProductVariant / ProductOption CRUD.
- **/admin/tenants** — tenant yönetimi (suspend, settings).
- **/admin/issues** — tüm tenant'ların ticket/claim'leri.

Aynı SPA içinde role-based router guard ile `/admin/*` rotaları açılır; ayrı bir build gerekmez.

---

## 19. Test Stratejisi

### 19.1 Backend (PHPUnit 12)
- **Unit**: servis sınıfları (`MappingRuleMatcher`, `OrderStatusService`, `ProductPricingService`) izolasyon testleri
- **Feature**: HTTP istek → response (her endpoint için happy + 4xx + auth/permission)
- **Tenant isolation**: cross-tenant erişim testleri
- **Migration rollback**: `php artisan migrate:fresh` her test setup'ta
- Faker + `database/factories/` factory'leri eksik modeller için tamamla

### 19.2 Frontend (Vitest 4)
- Pinia store unit testleri (`orders.ts`, `mappings.ts`)
- Component testleri (`MappingDialog`, `OrderWizard` adımları)
- API mock (msw veya manuel axios mock)

### 19.3 E2E (Playwright — eklenecek)
- Login → dashboard
- Yeni sipariş oluşturma uçtan uca
- CSV import preview → commit → required actions
- Mapping yaratma → action otomatik kapatma
- Issue açma → yorum ekleme → kapatma

### 19.4 Yük Testi
- k6 veya Artillery ile `/api/orders` listesi 100 RPS
- CSV import 10k satır → süre + memory

---

## 20. CI/CD ve Operasyon

### 20.1 GitHub Actions (mevcut `.github/`)
- PR'da: pint check, phpstan, phpunit, vitest, npm build
- Main merge: build artifact → staging deploy (envoy/octane/k8s tercih)
- Production deploy: tag-based (`v1.x.x`) + manuel onay

### 20.2 Production Stack (önerilen)
- Web: nginx + php-fpm + Octane (Swoole/RoadRunner) opsiyonel performans
- DB: PostgreSQL 16 managed (RDS / Cloud SQL / Neon)
- Cache/Queue: Redis 7 managed (Upstash / ElastiCache)
- Storage: S3 / R2
- Mail: Resend veya SES
- Logs: Laravel Pail (dev), Sentry + Datadog/CloudWatch (prod)
- WebSocket: Reverb servis süreci
- Worker: Horizon + Supervisor

### 20.3 Önerilen `.env` (kritik anahtarlar)
```
APP_ENV=production
APP_KEY=...
APP_URL=https://portal.example.com
DB_CONNECTION=pgsql
DB_HOST=...
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=s3
AWS_BUCKET=...
MAIL_MAILER=resend
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
SANCTUM_STATEFUL_DOMAINS=portal.example.com
SESSION_DOMAIN=.example.com
```

---

## 21. Tamamlanma Yol Haritası — Sprint Planı

> Mevcut iskelet üzerine, kalan iş yaklaşık 8-10 sprint (2'şer haftalık) ile bitirilebilir.

### Sprint 1 — Import & Required Action Kapama (P0, gap-report)
- ImportOrdersView'daki hardcoded sample'ı backend'e taşı
- XLSX import parser ekle
- `ProcessImportRowsJob` ile commit'i queue'ye al
- Required action tipleri için çözüm akışlarını tamamla (`invalid_artwork`, `duplicate_order`, `product_unavailable`)
- Auto-revalidate event listener (yeni mapping → failed item'lar yeniden mapping engine)

### Sprint 2 — Roles & Tenant Settings (P1)
- Owner rolünün UI'da görünmesi (`SettingsView.vue`)
- Users panel: davet etme, rol değiştirme, deaktive etme (`UserInvite` flow)
- Tenant ayarları paneli (support_email, default shipping service, settings JSON edit)
- Policy ve UI tutarlılık testleri

### Sprint 3 — Order Wizard Polish
- Adım state'inin auto-save'i (Pinia + localStorage + draft order)
- Frame Options swatch render (CSS variable ile renk preview)
- Live preview/mockup için stub component (gerçek render servisi Sprint 7'de)
- Step-level validation feedback (zorunlu alan eksik → görsel uyarı)

### Sprint 4 — Order Detail Polish
- Status timeline component (OrderStatusEvent'lerden)
- Totals breakdown card
- Tracking number → carrier deep-link
- Item-level "Replace Mapping" override butonu
- Design / Prints karşılaştırma slider

### Sprint 5 — Mapping & Bulk Ops
- Mapping edit/delete endpoint'leri ve UI
- Bulk mapping import (CSV)
- Mapping simulate UI hookup
- Orders list: checkbox + bulk cancel/tag/export
- Saved Views: kullanıcı tarafından kaydedilen filtre kombinasyonları

### Sprint 6 — Issues Real-time + Comments UX
- Laravel Reverb kurulumu + Echo client
- Issue thread'inde real-time yorum güncellemesi
- Attachment lightbox modal
- `@mention` ekleme + ilgili kullanıcıya bildirim
- In-app notification feed (topbar bell)

### Sprint 7 — Media Validation & Preview
- `MediaIntakeService` (DPI/color/bleed validasyonu)
- `PreviewRenderer` (uploaded design + product mockup composite)
- Reddedilen upload'lar için `invalid_artwork` required action
- Zip indirme: bir siparişin tüm template/design dosyalarını paketle

### Sprint 8 — Stripe Robustness + Reports
- Idempotency-Key uygulaması (mevcut endpoint'lere)
- `processed_webhook_events` tablosu + duplicate koruması
- Payment failure recovery akışı (required action)
- Refund tetikleyici (claim resolution → refund)
- `GET /api/reports/orders-summary` + Dashboard'da KPI kartları

### Sprint 9 — Webhooks & API Tokens
- Outbound webhook endpoint CRUD (UI: Settings → Webhooks)
- `OutboundWebhookDispatcher` queue job (HMAC imzalı)
- API Token UI (Sanctum personal_access_tokens üzerinde)
- Webhook retry log + failed event UI

### Sprint 10 — Producer Admin Panel
- `/admin/*` rotaları (role guard)
- Production kanban board
- Catalog CRUD (Type / Variant / Option)
- Tenant yönetim ekranı
- Tüm tenant'ları gören issue inbox

### Sprint 11 (Opsiyonel) — Polish & Launch
- Dark mode
- i18n (TR/EN ana, DE eklenebilir)
- Klavye kısayolları (cmd palette)
- Mobile responsive geçiş
- Erişilebilirlik (WCAG AA)
- Yük testi + güvenlik review (OWASP)
- Production go-live runbook (`docs/production-readiness.md` zaten var; tamamla)

---

## 22. Kabul Kriterleri (Tüm Sistemin)

- [ ] Müşteri kullanıcısı kayıt/davet ile katılır, login olur, dashboard'u görür.
- [ ] CSV import ile 1 ve 1000 sipariş yüklenebilir; her ikisi de doğru rapor üretir.
- [ ] Yeni sipariş wizard'ı uçtan uca tamamlanır, kapatıp açınca draft kalır.
- [ ] Mapping kurulduğunda eşleşmeyen siparişler otomatik `verified` olur.
- [ ] Stripe ödeme akışı happy + failed senaryolarında doğru durumla biter.
- [ ] Issue açılır, yorumlanır, kapatılır; tarafların biri yazınca diğer taraf real-time görür.
- [ ] Owner kullanıcısı diğer kullanıcılara rol atayabilir; viewer'lar değişiklik yapamaz.
- [ ] Cross-tenant data leak'i yoktur (test ile kanıtlanır).
- [ ] Tüm e-postalar `notification_mail_logs`'a düşer, unsubscribe çalışır.
- [ ] Production deploy + rollback runbook hazır.

---

## 23. Risk ve Bağımlılıklar

| Risk | Etki | Azaltma |
|---|---|---|
| Reverb prod'da kararsız | Realtime kaybolur | Fallback olarak SSE veya periodic polling |
| S3 upload failure | Kullanıcı dosyasını yükleyemez | Retry + chunked upload (tus.io protokolü ileri sprint) |
| Stripe API outage | Ödeme alınamaz | Webhook idempotency + recover queue |
| Large CSV (50k+) timeout | Import yarıda kalır | Queue + chunk + resume |
| `src/` ve `resources/js/` ikilemi | Geliştirici karışıklığı | `src/` arşivle veya kaldır (mevcut sprint başı görev) |
| Owner role UI eksikliği | Tenant yönetilemez | Sprint 2 önceliği |
| invalid_artwork validation eksikliği | Kötü dosya basıma gider | Sprint 7'ye kadar manuel kontrol süreci |

---

## 24. Dokümantasyon Kuralları (Eklenmesi Önerilen)

- `docs/api-contract.md` — her sprint sonunda yeni endpoint'lerle güncellensin (OpenAPI'ye geçilmesi öneriliyor).
- `docs/data-model.md` — ER diyagramı (Mermaid) + tablo açıklamaları.
- `docs/runbooks/` — operasyonel runbook'lar (deploy, rollback, queue stuck, DB migrate fail).
- `docs/onboarding.md` — yeni geliştirici 30 dakikada local setup tamamlasın.
- ADR (Architecture Decision Records) — major kararlar (Reverb vs Pusher, S3 vs R2, vb.) `docs/adr/`'de tutulsun.

---

Bu plan, **GELISTIRME_NOTLARI.md** ile birlikte okunmalıdır. Geliştirme notları referans sistemde tespit edilen UX/fonksiyonel eksiklikleri içerir ve sprint'lerin önceliklendirmesini etkiler — özellikle Sprint 3-7 arası alınacak kararlar bu notlardaki bulgularla birebir eşleşir.
