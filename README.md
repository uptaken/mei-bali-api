# Mei Bali Ops API

Backend Laravel untuk **Mei Bali Ops** — porting dari prototype frontend (`mei-bali-ops`, React/TS,
data in-memory) ke API sungguhan dengan database MySQL, mencakup:

- **Order** (Tour / Check In-Check Out-Transfer / Ticket) — pembuatan, penugasan Supplier/Driver +
  Mobil (bisa lebih dari satu kendaraan per hari), itinerary, add-ons.
- **Invoice** — satu invoice per order, Modal terisi otomatis dari Order, Harga Jual diisi manual.
- **Tagihan yang Perlu Dibayarkan (Payables)** — selalu *diturunkan* (derived) dari data Order/Invoice,
  satu Tagihan per pasangan Order↔Supplier (Transportasi, Tour, Ticket, Add-On, Layanan).
- **WhatsApp** — kirim konfirmasi ke Supplier/Driver lewat [Fonnte](https://fonnte.com), dengan log
  audit di tabel `whatsapp_messages` dan file log `storage/logs/whatsapp.log`.

> ✅ **Update**: project ini awalnya ditulis manual tanpa PHP/Composer di mesin pembuatnya, tapi
> sudah divalidasi sungguhan setelahnya — `composer install`, semua 24 migration, seeder, 45 route
> (`php artisan route:list`), `php artisan test`, dan alur HTTP penuh (login → buat order → dua
> vehicle/hari yang menghasilkan dua Tagihan terpisah → mark-paid → kirim WA simulasi → invoice
> pricing → mark billed → catat pembayaran → alur pembatalan Admin→Super Admin) semuanya sudah
> dicoba lewat `curl` sungguhan dan lolos. Dua bug nyata ketemu & sudah diperbaiki saat proses ini:
> nama tabel `WhatsAppTemplate`/`WhatsAppMessage` yang salah tebak oleh konvensi Eloquent, dan
> `public/index.php` (front controller) yang kelupaan ditulis. Laravel juga dinaikkan dari `^11.31`
> ke **`^12.0`** karena baris 11.x yang lama sudah kena advisory keamanan di Packagist.

## 0. PHP/Composer portabel (sudah disiapkan, tidak perlu install apa pun)

Karena mesin ini tidak punya PHP/Composer/Homebrew, folder `.php-portable/` di sini berisi PHP 8.3
statis (dari [static-php-cli](https://static-php.dev), sudah termasuk `pdo_mysql`, `pdo_sqlite`,
`mbstring`, `curl`, `zip`, dll) + `composer.phar`, dan dua wrapper script di root project:

```bash
./php artisan --version      # bukan `php`, tapi `./php`
./composer install           # bukan `composer`, tapi `./composer`
```

Kalau Anda sudah/nanti punya PHP 8.2+ & Composer sendiri di PATH, pakai itu saja seperti biasa
(`php artisan ...`, `composer ...`) — `.php-portable/` boleh dihapus kapan saja, tidak dipakai oleh
kode aplikasinya sendiri.

## 1. Prasyarat

- PHP 8.2+ dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` (sudah tersedia lewat `./php` di atas)
- Composer 2.x (sudah tersedia lewat `./composer` di atas)
- MySQL 8 (atau MariaDB 10.6+) — **atau SQLite** untuk mulai cepat tanpa server database sama sekali (lihat §2)
- Akun [Fonnte](https://fonnte.com) (opsional untuk mulai — bisa dilewati dulu, lihat §4)

Kalau mau pakai PHP/Composer sendiri via Homebrew nanti:

```bash
brew install php composer mysql
brew services start mysql
```

## 2. Install

```bash
cd mei-bali-api
./composer install     # atau: composer install, kalau sudah punya Composer sendiri
cp .env.example .env
./php artisan key:generate
```

**Opsi tercepat untuk langsung coba tanpa MySQL** — pakai SQLite (sudah terbukti jalan di §"Update" atas):

```bash
sed -i '' 's/^DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env
touch database/database.sqlite
# isi DB_DATABASE dengan path absolut ke file itu, misal:
echo "DB_DATABASE=$(pwd)/database/database.sqlite" >> .env
./php artisan migrate --seed
./php artisan serve
```

Lanjut ke §2a kalau memang mau pakai MySQL untuk produksi/development yang lebih dekat ke real.

### 2a. Setup MySQL (kalau tidak pakai SQLite)

Buat database-nya (sesuaikan kalau `mysql` butuh password):

```bash
mysql -u root -e "CREATE DATABASE mei_bali_ops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Isi `.env` — minimal ini yang perlu dicek/diubah:

```
DB_DATABASE=mei_bali_ops
DB_USERNAME=root
DB_PASSWORD=

# alamat frontend React yang boleh memanggil API ini
FRONTEND_URL=http://localhost:5199
SANCTUM_STATEFUL_DOMAINS=localhost:5199,127.0.0.1:5199
```

Migrasi + seed data contoh (client, supplier, mobil, produk, 2 order contoh lengkap dengan invoice):

```bash
./php artisan migrate --seed
```

> ⚠️ Kalau ganti `DB_CONNECTION` ke `sqlite` tapi `DB_DATABASE` masih berisi nilai lama seperti
> `mei_bali_ops` (bukan path file), SQLite akan diam-diam membuat file baru bernama `mei_bali_ops`
> (tanpa ekstensi) di root project alih-alih memakai `database/database.sqlite` — ini yang sempat
> kejadian saat divalidasi. Selalu isi `DB_DATABASE` dengan path absolut ke file `.sqlite`-nya kalau
> pakai SQLite (lihat contoh `echo "DB_DATABASE=..."` di §2).

Kalau seed sukses, akan ada 3 user contoh — semua dengan password `password`:

| Email | Role |
|---|---|
| ayu.kartika@meibali.com | Super Admin |
| sinta.wulandari@meibali.com | Admin |
| made.wirawan@meibali.com | Operator |

## 3. Jalankan

```bash
./php artisan serve
# API jalan di http://127.0.0.1:8000
```

Queue worker (wajib jalan supaya pesan WhatsApp benar-benar terkirim — pengiriman WA di-queue,
bukan langsung, biar request API tidak nunggu Fonnte):

```bash
./php artisan queue:work
```

Login untuk dapat token (dipakai di header `Authorization: Bearer <token>` untuk semua endpoint lain):

```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ayu.kartika@meibali.com","password":"password"}'
```

## 4. Setup WhatsApp (Fonnte)

1. Daftar di https://fonnte.com, tambah **Device** baru, scan QR pakai WhatsApp yang mau dipakai kirim
   pesan (nomor khusus operasional, bukan nomor pribadi).
2. Setelah device connect, copy **Token** dari dashboard perangkat tersebut.
3. Isi di `.env`:
   ```
   FONNTE_TOKEN=isi_token_dari_dashboard
   FONNTE_ENABLED=true
   ```
4. Sebelum `FONNTE_ENABLED=true` diisi (atau token kosong), semua pengiriman WA **disimulasikan** —
   dicatat ke `whatsapp_messages` table dan `storage/logs/whatsapp.log` dengan status `sent`, tapi
   tidak benar-benar keluar ke WhatsApp. Aman dipakai untuk development tanpa boros kuota WA.

Test kirim WA langsung (tanpa lewat Order):

```bash
curl -X POST http://127.0.0.1:8000/api/whatsapp/send \
  -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -d '{"phone":"081234567890","message":"Test dari Mei Bali Ops API"}'
```

Kirim konfirmasi ke Supplier/Driver yang sudah ditugaskan di sebuah Order:

```bash
curl -X POST http://127.0.0.1:8000/api/orders/1/send-wa-supplier \
  -H "Authorization: Bearer <token>"
```

## 5. Ringkasan Endpoint

Semua endpoint (kecuali `/api/login`) butuh header `Authorization: Bearer <token>`.
Yang butuh role Admin/Super Admin ditandai 🔒.

| Method | Path | Keterangan |
|---|---|---|
| POST | `/api/login` | Login, dapat token |
| GET | `/api/me` | Profil user login |
| GET | `/api/clients`, `/api/suppliers`, `/api/vehicles`, `/api/products` | Master data |
| POST/PATCH/DELETE ke path di atas | 🔒 | Kelola master data |
| GET | `/api/orders` | List order (filter: `tipe`, `status`, `supplier_id`, `date_from`, `date_to`, `q`) |
| GET | `/api/orders/{id}` | Detail order + itinerary/assignment/invoice |
| POST | `/api/orders` | 🔒 Buat order baru (satu payload untuk Tour/Layanan/Ticket sekaligus, langsung bikin Invoice) |
| PATCH | `/api/orders/{id}` | 🔒 Ubah field dasar order |
| PATCH | `/api/orders/{id}/operational` | 🔒 Operational & Reservasi — assignment (bisa lebih dari satu mobil/hari), add-ons, harga itinerary |
| POST | `/api/orders/{id}/selesai` | 🔒 Tandai selesai |
| POST | `/api/orders/{id}/cancel` | 🔒 Batalkan (langsung untuk Super Admin, jadi permintaan untuk Admin) |
| POST | `/api/orders/{id}/send-wa-supplier` | Kirim WA konfirmasi ke Supplier/Driver yang ditugaskan |
| GET | `/api/invoices`, `/api/invoices/{id}` | List/detail invoice |
| PATCH | `/api/invoices/{id}/lines` | Isi/ubah Harga Jual per baris |
| POST | `/api/invoices/{id}/mark-billed` \| `/unmark-billed` | Status penagihan |
| POST | `/api/invoices/{id}/payments` | Catat pembayaran masuk |
| GET | `/api/payables` | Tagihan yang Perlu Dibayarkan (list, selalu diturunkan) |
| GET | `/api/payables/{id}` | Detail satu Tagihan |
| POST | `/api/payables/{id}/mark-paid` | Tandai satu Tagihan lunas |
| POST | `/api/payables/mark-paid-bulk` | Tandai banyak Tagihan lunas sekaligus |
| POST | `/api/whatsapp/send` | Kirim WA bebas (tanpa terkait Order) |

## 6. Test

```bash
./php artisan test
```

Ada satu Feature test (`tests/Feature/OrderAndPayablesTest.php`) yang membuktikan skenario "1 mobil
tidak cukup": dua kendaraan di hari yang sama pada satu Order tour, dengan dua Supplier berbeda,
harus menghasilkan dua baris invoice Transport terpisah **dan** dua Tagihan terpisah di Payables.

## 7. Struktur yang sengaja belum dibuat (di luar cakupan "core")

Menyusul kalau dibutuhkan: Reporting/dashboard analytics, Package (paket tur) CRUD penuh, Approval
workflow (saat ini pembatalan Admin→Super Admin sudah ada, tapi belum ada halaman daftar approval
terpisah), WhatsApp Template CRUD (datanya sudah ada di seed, endpoint kelola belum), export Excel
invoice.
