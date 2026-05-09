# Production Readiness

## Hedef
Projeyi bir sonraki adımda doğrudan dağıtıma hazır bir hale getirmek için local geliştirme ve test zinciri aşağıdaki bileşenleri kapsar:
- AlmaLinux 9 tabanlı PHP/Node çalışma katmanı
- PostgreSQL tabanlı kalıcı veritabanı
- Redis (cache + queue)
- Queue worker
- Mailpit (SMTP test)
- MinIO (S3 uyumlu medya depolama)
- CI hattı (backend test, frontend test, build, formatter)

## Docker ile local geliştirme

```bash
cp .env.example .env
docker compose up --build
```

Uygulama URL’i: `http://localhost:8010`  
Mailpit UI: `http://localhost:8025`  
MinIO console: `http://localhost:9001`

## Ortam değişkenleri

- Varsayılan `.env.example` yerel ve container için güvenli başlangıç değerleri içerir.
- Üretimde `QUEUE_CONNECTION=redis`, `DB_CONNECTION=pgsql`, `MAIL_MAILER=smtp` ve `FILESYSTEM_DISK=s3` kombinasyonu önerilir.
- Production için `APP_KEY`, PII odaklı loglama politikası ve mail/SMTP kullanıcıları gerçek değerlere taşınmalı.

## CI

`/.github/workflows/ci.yml` dosyasında:
- `php artisan test`
- `./vendor/bin/pint --test`
- `npm run test:unit`
- `npm run build`

iş akışları birlikte çalışır.

## Security / operasyon notları

- API trafiğine default `throttle:120,1` eklendi (`routes/web.php` içinde `/api` grubu).
- Yükleme/özellikler için mime/size doğrulamaları mevcut durumda medya uçlarında korunmalı.
- Queue job hataları ve bildirim logları kritik akışlarda izlenebilir.
- MinIO + Mailpit yalnızca local geliştirme kolaylığı içindir; prod için güvenilir managed servisler tercih edin.
