# Javapucino POS

Sistem Point of Sale (kasir) & CMS untuk booth kopi Javapucino.
Dibangun dengan **Laravel 12** dan **PHP 8.3**, database **MySQL/MariaDB** bernama `javapucino`.

Implementasi mengikuti dokumen **FRD & ERD Sistem POS Booth Kopi v1.0** dan **Mockup Javapucino POS v1**.

---

## 1. Kebutuhan

| Komponen | Versi |
|---|---|
| PHP | 8.3 (ekstensi: pdo_mysql, mbstring, openssl, fileinfo, intl, curl) |
| Composer | 2.x |
| MySQL / MariaDB | MySQL 8 atau MariaDB 10.4+ (bawaan Laragon) |
| Web server | Laragon (Apache/Nginx) atau `php artisan serve` |

Tidak memerlukan Node.js / npm — seluruh CSS dan JS sudah berupa file statis di `public/`.

## 2. Instalasi di Laragon

1. Ekstrak folder project ke `C:\laragon\www\javapucino`.
2. Buka **Laragon → Terminal**, lalu:

   ```bash
   cd C:\laragon\www\javapucino
   composer install
   copy .env.example .env
   php artisan key:generate
   ```

3. Buka **phpMyAdmin** (`http://localhost/phpmyadmin`) → **New** → buat database
   bernama `javapucino` dengan collation `utf8mb4_unicode_ci`.

4. Pastikan bagian ini di `.env` sudah sesuai:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=javapucino
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. Jalankan migrasi beserta data awal:

   ```bash
   php artisan migrate --seed
   php artisan storage:link
   ```

6. Jalankan aplikasi:

   ```bash
   php artisan serve
   ```

   Buka `http://127.0.0.1:8000`.
   Bila memakai virtual host Laragon (`http://javapucino.test`), arahkan document root
   ke folder `public/` dan sesuaikan `APP_URL` di `.env`.

## 3. Akun demo

| Nama | Username | Peran | Status |
|---|---|---|---|
| Bagas Prakoso | `admin` | Administrator | aktif |
| Rina Oktaviani | `rina` | Kasir | aktif |
| Dimas Aryo | `dimas` | Kasir | aktif |
| Sella Maharani | `sella` | Kasir | nonaktif (untuk menguji BR-14) |

Kata sandi seluruh akun: **`password123`**

> Ganti kata sandi sebelum dipakai di lingkungan produksi, dan set `APP_DEBUG=false`.

## 4. Peta halaman

| Area | URL | Keterangan |
|---|---|---|
| Publik | `/menu` | Menu board + papan antrian (tanpa login) |
| Auth | `/login`, `/logout` | Diarahkan sesuai peran |
| Kasir | `/kasir/shift` | Buka shift (FR-02) |
| Kasir | `/kasir` | Portal kasir / POS (FR-05 s.d. FR-12) |
| Kasir | `/kasir/antrian` | Kontrol nomor antrian |
| Kasir | `/kasir/riwayat` | Riwayat shift aktif (FR-15) |
| Kasir | `/kasir/tutup-shift` | Rekonsiliasi kas (FR-16) |
| Umum | `/struk/{id}`, `/struk/{id}/cetak` | Struk & cetak thermal (FR-13, FR-14) |
| Admin | `/admin/dashboard` | KPI harian (FR-17) |
| Admin | `/admin/products`, `/categories`, `/materials`, `/users` | CRUD data master |
| Admin | `/admin/transaksi` | Daftar, void, export (FR-22, FR-23) |
| Admin | `/admin/laporan` | Laporan periode (FR-24) |
| Admin | `/admin/audit-log` | Audit log read-only (FR-25) |
| Admin | `/admin/pengaturan` | Pengaturan toko & struk (FR-26) |

## 5. Struktur penting

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/LoginController.php
│   │   ├── Kasir/{PosController,ShiftController,QueueController}.php
│   │   ├── Admin/{Dashboard,Product,Category,Material,User,Transaction,Report,AuditLog,Setting}Controller.php
│   │   ├── BoardController.php          # layar pelanggan
│   │   └── ReceiptController.php        # struk & cetak
│   └── Middleware/{RoleMiddleware,EnsureShiftIsOpen}.php
├── Models/                              # 10 model sesuai ERD
├── Services/
│   ├── PosService.php                   # checkout & void (DB transaction + lockForUpdate)
│   ├── StockService.php                 # pencatatan stock_movements (BR-12)
│   └── AuditLogger.php                  # audit append-only (BR-11)
└── Support/helpers.php                  # setting(), rupiah(), angka(), tax_of()

database/migrations/                     # 12 migration sesuai kamus data FRD
database/seeders/                        # data master + demo 7 hari
resources/views/                         # Blade: layouts, kasir, admin, board, receipt
public/css/app.css                       # style asli dari mockup v1
public/js/{app.js,pos.js}                # interaksi UI & keranjang kasir
config/javapucino.php                    # konfigurasi khusus aplikasi
```

## 6. Aturan bisnis yang sudah diterapkan

| Kode | Penerapan |
|---|---|
| BR-01 | `Transaction::nextInvoiceNo()` — `TRX-YYYYMMDD-NNNN`, reset harian, dikunci `lockForUpdate()` |
| BR-02 | `tax_of()` + kolom `DECIMAL(12,2)` |
| BR-03 | Validasi cash/QRIS di `PosService::checkout()` |
| BR-04 | Snapshot `product_name`, `price`, `hpp` ke `transaction_items` |
| BR-05 | Stok tidak boleh negatif; menu stok 0 tidak dapat dipilih & tampil "Habis" |
| BR-06 | Transaksi tidak dapat diedit/dihapus; koreksi hanya via void |
| BR-07 | Middleware `shift.open` + satu shift `open` per kasir |
| BR-08 | `Shift::expectedCash()` dan perhitungan selisih saat tutup shift |
| BR-09 | Soft delete pada `users`, `categories`, `products`, `materials` |
| BR-10 | Kategori dengan produk aktif tidak dapat dihapus |
| BR-11 | Audit log tanpa aksi ubah/hapus di aplikasi |
| BR-12 | Setiap perubahan stok mencatat `stock_before` & `stock_after` |
| BR-13 | Status "Menipis" (bahan) dan "Hampir habis" (produk) sesuai ambang di pengaturan |
| BR-14 | Pengguna nonaktif tidak bisa login; admin tidak bisa menonaktifkan dirinya sendiri |

## 7. Di luar lingkup v1

Sesuai Bab 11 FRD, hal berikut belum diimplementasikan: QRIS dinamis via payment gateway,
multi-cabang, diskon/promo, varian menu, dan pemotongan stok bahan otomatis berbasis resep.
Kolom `qris_reference` sudah disiapkan untuk pengembangan QRIS dinamis.

## 8. Catatan tambahan

- **Antrian**: mockup memperkenalkan nomor antrian yang belum ada di FRD v1.0, sehingga kolom
  `queue_no` dan `queue_status` ditambahkan ke tabel `transactions`. Layar pelanggan
  menyegarkan nomor lewat polling JSON setiap 5 detik ke `/menu/antrian`.
- **Cetak struk**: memakai print browser (58mm/80mm). Setiap pencetakan menambah
  `printed_count` dan tercatat di audit log.
- **Export**: daftar transaksi diekspor sebagai CSV UTF-8 (langsung terbaca di Excel).
  Laporan dicetak melalui dialog print browser (Save as PDF).
- **Timezone**: `Asia/Jakarta`, format Rupiah `Rp 25.000`.
