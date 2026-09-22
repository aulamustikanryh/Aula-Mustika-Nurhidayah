# JASMAT

Aplikasi manajemen jasa berbasis web untuk mengelola data jasa/pesanan dengan sistem **register/login**. Setiap user hanya dapat melihat dan mengelola data jasa miliknya sendiri yang disimpan di **MySQL**.

## Struktur Proyek

```text
jasmat/
   ├── index.php          # Halaman utama aplikasi (wajib login)
   ├── config.php         # Koneksi ke database MySQL
   ├── database.sql       # Struktur tabel users & jasa
   ├── README.md          # Dokumen ini
   ├── public/
   │   ├── css/
   │   │   └── style.css  # Styling seluruh halaman
   │   └── js/
   │       └── script.js  # Logic frontend: render, validasi, fetch ke API
   └── views/
      ├── login.php       # Halaman masuk
      ├── register.php    # Halaman daftar akun
      ├── logout.php      # Proses keluar (hapus session)
      └── api/
         └── jasa.php     # Endpoint CRUD jasa (GET/POST/PATCH/DELETE)
```

## Fitur

* **Register & Login** — pengguna dapat membuat akun dan masuk ke aplikasi. Password disimpan dalam bentuk hash menggunakan `password_hash()`.
* **Data per-user** — data jasa yang dibuat hanya dapat dilihat, diubah, dan dihapus oleh akun pemiliknya. Akses diperiksa menggunakan `user_id` dan session di server.
* **Tambah jasa** — pengguna dapat menambahkan data jasa baru berupa nama jasa, deskripsi, harga, deadline, dan status.
* **Edit jasa** — pengguna dapat mengubah informasi jasa yang sudah dibuat.
* **Hapus jasa** — pengguna dapat menghapus data jasa miliknya sendiri.
* **Status jasa** — data jasa memiliki tiga status yaitu `pending`, `progress`, dan `done`.
* **Cari/filter jasa** — pengguna dapat mencari data berdasarkan nama atau deskripsi jasa secara real-time di sisi client.
* **Validasi form** — validasi dilakukan di sisi client menggunakan JavaScript dan di sisi server menggunakan PHP. Nama jasa tidak boleh kosong dan harga harus valid.
* **Notifikasi** — menggunakan SweetAlert2 melalui CDN untuk memberikan notifikasi ketika data berhasil ditambah, diubah, atau dihapus.
* **Aman dari XSS** — data jasa ditampilkan menggunakan `textContent`, bukan `innerHTML`.
* **Aman dari SQL Injection** — seluruh query database menggunakan prepared statement dengan PDO.
* **Session Authentication** — halaman utama dan API hanya dapat digunakan oleh user yang sudah login.
* **Fetch API** — proses CRUD data jasa dilakukan menggunakan `fetch()` ke endpoint `api/jasa.php`.

## Cara Menjalankan di Lokal

Butuh environment **PHP + MySQL**, misalnya **XAMPP**:

1. Copy folder `jasmat/` ke dalam folder `htdocs`.

2. Jalankan **Apache** dan **MySQL** melalui XAMPP.

3. Buat database melalui phpMyAdmin lokal:

   `http://localhost/phpmyadmin`

4. Import file `database.sql` ke phpMyAdmin.

5. Sesuaikan `config.php` dengan kredensial database lokal.

   Konfigurasi default XAMPP:

```php
$host = 'localhost';
$db   = 'jasmat';
$user = 'root';
$pass = '';
```

6. Akses aplikasi melalui browser:

```text
http://localhost/jasmat/
```

7. Buat akun melalui halaman **Register**, kemudian login menggunakan akun tersebut.

## Struktur Data

**Tabel `users`**: `id`, `username`, `password_hash`, `created_at`

**Tabel `jasa`**: `id`, `user_id`, `nama_jasa`, `deskripsi`, `harga`, `deadline`, `status`, `created_at`, `updated_at`

### Status Jasa

| Status     | Keterangan                          |
| ---------- | ----------------------------------- |
| `pending`  | Jasa belum mulai dikerjakan         |
| `progress` | Jasa sedang dalam proses pengerjaan |
| `done`     | Jasa telah selesai dikerjakan       |

## API Endpoint

Endpoint CRUD utama berada di:

```text
views/api/jasa.php
```

Method yang digunakan:

```text
GET       → mengambil data jasa
POST      → menambahkan jasa
PATCH     → mengubah jasa
DELETE    → menghapus jasa
```

Semua proses API menggunakan session user sehingga user hanya dapat mengakses data miliknya sendiri.

## Alur Sistem

```text
Register
    ↓
Login
    ↓
Dashboard JASMAT
    ↓
Melihat Data Jasa
    ↓
┌───────────────┬───────────────┐
│               │               │
Tambah         Edit            Hapus
│               │               │
└───────────────┴───────────────┘
                ↓
          Database MySQL
                ↓
      Update Status Jasa
                ↓
             Logout
```

## Teknologi yang Digunakan

* **PHP** — backend aplikasi
* **MySQL** — penyimpanan database
* **HTML** — struktur halaman
* **CSS** — tampilan aplikasi
* **JavaScript** — logic frontend
* **PDO** — koneksi dan query database
* **Fetch API** — komunikasi frontend dengan backend
* **Session** — autentikasi pengguna
* **SweetAlert2** — notifikasi aplikasi

## Keamanan

Aplikasi JASMAT menerapkan beberapa keamanan dasar:

* Password menggunakan `password_hash()`.
* Password login diverifikasi menggunakan `password_verify()`.
* Query database menggunakan **PDO Prepared Statement**.
* Data pengguna dipisahkan berdasarkan `user_id`.
* API memeriksa session sebelum memberikan akses.
* Output teks pada frontend menggunakan `textContent` untuk mengurangi risiko XSS.
* Input juga divalidasi kembali di sisi server sehingga tidak hanya bergantung pada validasi JavaScript.

## Debugging

Buka **Browser DevTools → Console** untuk melihat log atau error dari JavaScript dan proses `fetch()`.

Jika terjadi error koneksi database, periksa kembali:

```text
config.php
```

Pastikan:

* Apache sudah aktif.
* MySQL sudah aktif.
* Nama database adalah `jasmat`.
* Username MySQL benar.
* Password MySQL benar.
* File `database.sql` sudah di-import.
* Folder project berada di dalam `htdocs`.

Jika terjadi error pada API, periksa:

```text
views/api/jasa.php
```

dan gunakan **DevTools → Network** untuk melihat request dan response API.
