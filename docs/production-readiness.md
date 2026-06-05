# Production Readiness

## Hedef
Projeyi bir sonraki adımda doğrudan dağıtıma hazır bir hale getirmek için local geliştirme ve test zinciri aşağıdaki bileşenleri kapsar:
- macOS/Homebrew üzerinde PHP 8.3 + Node çalışma katmanı
- PostgreSQL tabanlı kalıcı veritabanı
- Redis (cache + queue)
- Queue worker
- Laravel'in local mail/log sürücüleri
- Local disk veya production için S3 uyumlu medya depolama
- CI hattı (backend test, frontend test, build, formatter)

## Yerel geliştirme

```bash
cp .env.example .env
brew services start postgresql@16
php artisan migrate --seed
composer run dev
```

Uygulama URL’i: `http://127.0.0.1:8000`

## Ortam değişkenleri

- Varsayılan `.env.example` yerel PostgreSQL için güvenli başlangıç değerleri içerir.
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
- Production mail ve storage için güvenilir managed servisler tercih edin.
