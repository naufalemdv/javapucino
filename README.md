# Javapucino POS

Sistem Point of Sale (kasir) & CMS untuk booth kopi Javapucino.
Dibangun dengan **Laravel 12** dan **PHP 8.3**, database **MySQL/MariaDB** bernama `javapucino`.

Implementasi mengikuti dokumen **FRD & ERD Sistem POS Booth Kopi v1.0** dan **Mockup Javapucino POS v1**,
ditambah modul keuangan (FR-27, FR-28) yang dikembangkan setelah v1.0.

---

## 1. Kebutuhan

| Komponen | Versi |
| --- | --- |
| PHP | 8.3 (ekstensi: pdo_mysql, mbstring, openssl, fileinfo, intl, curl, zip, gd) |
| Composer | 2.x |
| MySQL / MariaDB | MySQL 8 atau MariaDB 10.4+ (bawaan Laragon) |
| Web server | Laragon (Apache/Nginx) atau `php artisan serve` |

Tidak memerlukan Node.js / npm — seluruh CSS dan JS sudah berupa file statis di `public/`.

> Ekstensi `zip` dan `gd` dibutuhkan oleh PhpSpreadsheet untuk menghasilkan file `.xlsx`.

## 2. Instalasi di Laragon

1. Ekstrak folder project ke `C:\laragon\www\javapucino`.

2. Buka **Laragon → Terminal**, lalu:

```
cd C:\laragon\www\javapucino
composer install
copy .env.example .env
php artisan key:generate
```

3. Buka **phpMyAdmin** (`http://localhost/phpmyadmin`) → **New** → buat database
   bernama `javapucino` dengan collation `utf8mb4_unicode_ci`.

4. Pastikan bagian ini di `.env` sudah sesuai:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=javapucino
DB_USERNAME=root
DB_PASSWORD=
```

5. Jalankan migrasi beserta data awal:

```
php artisan migrate --seed
php artisan storage:link
```

6. Jalankan aplikasi:

```
php artisan serve
```

Buka `http://127.0.0.1:8000`.
Bila memakai virtual host Laragon (`http://javapucino.test`), arahkan document root
ke folder `public/` dan sesuaikan `APP_URL` di `.env`.

## 3. Akun demo

| Nama | Username | Peran | Status |
| --- | --- | --- | --- |
| Bagas Prakoso | `admin` | Administrator | aktif |
| Rina Oktaviani | `rina` | Kasir | aktif |
| Dimas Aryo | `dimas` | Kasir | aktif |
| Sella Maharani | `sella` | Kasir | nonaktif (untuk menguji BR-14) |

Kata sandi seluruh akun: **`password123`**

> Ganti kata sandi sebelum dipakai di lingkungan produksi, dan set `APP_DEBUG=false`.

## 4. Peta halaman

| Area | URL | Keterangan |
| --- | --- | --- |
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
| Admin | `/admin/transaksi` | Daftar, void, export CSV (FR-22, FR-23) |
| Admin | `/admin/laporan` | Laporan periode + export Excel (FR-24) |
| Admin | `/admin/shift` | Rekap shift & selisih kas (FR-28) |
| Admin | `/admin/pengeluaran` | Laba bersih bulanan & CRUD pengeluaran (FR-27) |
| Admin | `/admin/pengeluaran/kategori` | Kelola kategori pengeluaran |
| Admin | `/admin/audit-log` | Audit log read-only (FR-25) |
| Admin | `/admin/pengaturan` | Pengaturan toko & struk (FR-26) |

## 5. Alur perhitungan keuangan

```
Omzet                                   ← penjualan completed (tunai + QRIS)
 − HPP                = Laba kotor      ← modal bahan yang terjual (FR-24)
 − Biaya operasional  = Laba bersih     ← listrik, wifi, gaji, sewa (FR-27)
```

Pembelian bahan **tidak** mengurangi laba bersih, karena modal bahan sudah terwakili oleh HPP.
Belanja bahan tetap dicatat nilainya sebagai arus kas keluar dan sebagai pembanding
"HPP estimasi vs belanja aktual" (BR-16).

Rekonsiliasi kas tunai berdiri sendiri, terpisah dari perhitungan laba:

```
Modal awal + Penjualan tunai = Kas seharusnya
Kas aktual − Kas seharusnya  = Selisih        ← diawasi lewat FR-28
```

Modal kas kembalian bukan biaya, melainkan saldo pembuka laci yang dicatat di
`shifts.opening_cash`. QRIS tidak ikut dihitung karena tidak masuk laci.

## 6. Struktur penting

```
app/
├── Console/Commands/
│   └── ResetTransactionData.php         # php artisan javapucino:reset-transaksi
├── Http/
│   ├── Controllers/
│   │   ├── Auth/LoginController.php
│   │   ├── Kasir/{PosController,ShiftController,QueueController}.php
│   │   ├── Admin/{Dashboard,Product,Category,Material,User,Transaction,
│   │   │          Report,ShiftReport,Expense,ExpenseCategory,AuditLog,Setting}Controller.php
│   │   ├── BoardController.php          # layar pelanggan
│   │   └── ReceiptController.php        # struk & cetak
│   └── Middleware/{RoleMiddleware,EnsureShiftIsOpen}.php
├── Models/                              # 12 model (10 sesuai ERD + Expense, ExpenseCategory)
├── Services/
│   ├── PosService.php                   # checkout & void (DB transaction + lockForUpdate)
│   ├── StockService.php                 # pencatatan stock_movements (BR-12)
│   ├── FinanceService.php               # rekap bulanan omzet/HPP/laba (FR-27)
│   └── AuditLogger.php                  # audit append-only (BR-11)
└── Support/helpers.php                  # setting(), rupiah(), angka(), tax_of()

database/migrations/                     # 16 migration (12 sesuai kamus data FRD + 4 modul keuangan)
database/seeders/                        # data master, kategori pengeluaran, demo 7 hari
resources/views/                         # Blade: layouts, partials, kasir, admin, board, receipt
public/css/app.css                       # style asli dari mockup v1
public/js/{app.js,pos.js}                # interaksi UI & keranjang kasir
config/javapucino.php                    # konfigurasi khusus aplikasi
```

## 7. Aturan bisnis yang sudah diterapkan

### v1.0 — inti POS

| Kode | Penerapan |
| --- | --- |
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

### v1.1 — modul keuangan

| Kode | Penerapan |
| --- | --- |
| BR-15 | Nominal pengeluaran wajib lebih dari 0; tidak menerima nilai negatif |
| BR-16 | Hanya kategori `operational` yang mengurangi laba kotor; `inventory` hanya masuk arus kas keluar |
| BR-17 | Setiap create/update/delete pengeluaran tercatat di audit log dengan nilai lama & baru |
| BR-18 | Kategori bawaan dan kategori yang masih dipakai tidak dapat dihapus |
| BR-19 | Soft delete pada `expenses` dan `expense_categories` |
| BR-20 | Pengeluaran jenis `inventory` hanya dibuat dari restock bahan; tidak dapat diubah/dihapus dari menu Pengeluaran |

## 8. Di luar lingkup v1

Sesuai Bab 11 FRD, hal berikut belum diimplementasikan: QRIS dinamis via payment gateway,
multi-cabang, diskon/promo, varian menu, dan pemotongan stok bahan otomatis berbasis resep.
Kolom `qris_reference` sudah disiapkan untuk pengembangan QRIS dinamis.

Ditambahkan sejak modul keuangan:

- **Biaya rutin otomatis** — biaya bulanan seperti wifi belum dibuat otomatis tiap bulan.
  Sebagai gantinya, halaman Pengeluaran menampilkan pengingat kategori yang ada di bulan lalu
  tapi belum tercatat bulan ini.
- **Kas masuk/keluar (prive)** — penarikan uang tunai oleh pemilik belum punya pencatatan sendiri.
- **Biaya admin QRIS** — potongan MDR tidak dihitung otomatis; dicatat manual sebagai kategori
  pengeluaran operasional setelah pengecekan mutasi rekening.
- **Perhitungan gaji dari jam kerja** — tabel `shifts` tidak menyimpan durasi kerja,
  sehingga gaji dicatat manual sebagai pengeluaran.

## 9. Catatan tambahan

- **Antrian**: mockup memperkenalkan nomor antrian yang belum ada di FRD v1.0, sehingga kolom
  `queue_no` dan `queue_status` ditambahkan ke tabel `transactions`. Layar pelanggan
  menyegarkan nomor lewat polling JSON setiap 5 detik ke `/menu/antrian`.
- **Cetak struk**: memakai print browser (58mm/80mm). Setiap pencetakan menambah
  `printed_count` dan tercatat di audit log.
- **Export**:
  - Daftar transaksi → CSV UTF-8 (langsung terbaca di Excel)
  - Laporan penjualan, rekap shift, dan pengeluaran → `.xlsx` via PhpSpreadsheet
  - Laporan juga dapat dicetak melalui dialog print browser (Save as PDF)
- **Harga bahan**: `materials.unit_cost` menyimpan harga beli terakhir sebagai nilai awal
  form restock, sedangkan `stock_movements.unit_cost` menyimpan harga pada saat pergerakan
  agar riwayat tidak berubah ketika harga acuan diperbarui.
- **Timezone**: `Asia/Jakarta`, format Rupiah `Rp 25.000`. Batas bulan pada laporan keuangan
  mengikuti zona waktu ini, jadi `APP_TIMEZONE` wajib diisi `Asia/Jakarta`.

## 10. Perintah pemeliharaan

Mengosongkan data transaksi untuk mulai memakai data asli. Data master (produk, kategori,
bahan, pengguna, pengaturan) tidak disentuh:

```
php artisan javapucino:reset-transaksi
php artisan javapucino:reset-transaksi --stok      # sekaligus menolkan stok
```

Perintah ini meminta konfirmasi sebelum berjalan dan mengosongkan tabel
`transactions`, `transaction_items`, `shifts`, `expenses`, `stock_movements`, dan `audit_logs`.

> Backup database sebelum menjalankannya, dan hapus file perintah ini
> (`app/Console/Commands/ResetTransactionData.php`) bila sistem sudah dipakai produksi.