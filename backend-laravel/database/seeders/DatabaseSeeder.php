<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\SiwisHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $uploadDir = SiwisHelper::getUploadDirectory();

        // Bersihkan folder upload
        $files = glob($uploadDir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== '.gitkeep') {
                @unlink($file);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        $tables = [
            'personal_access_tokens',
            'log_aktivitas',
            'notifikasi',
            'jadwal_wisuda',
            'peserta_wisuda',
            'pembayaran',
            'berkas_wisuda',
            'pendaftaran_wisuda',
            'periode_wisuda',
            'mahasiswa',
            'users',
        ];
        foreach ($tables as $t) {
            DB::statement("TRUNCATE TABLE {$t}");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        // Pastikan jenis_berkas ada
        $jenisCount = DB::table('jenis_berkas')->count();
        if ($jenisCount === 0) {
            DB::table('jenis_berkas')->insert([
                ['nama' => 'Bukti Pembayaran', 'keterangan' => 'Bukti transfer biaya wisuda (JPG/PNG/PDF)', 'wajib' => 1, 'is_pembayaran' => 1, 'aktif' => 1, 'urutan' => 1],
                ['nama' => 'Surat Bebas Pustaka', 'keterangan' => 'Surat keterangan bebas pinjaman dari perpustakaan', 'wajib' => 1, 'is_pembayaran' => 0, 'aktif' => 1, 'urutan' => 2],
                ['nama' => 'Surat Bebas Laboratorium', 'keterangan' => 'Surat keterangan bebas laboratorium', 'wajib' => 1, 'is_pembayaran' => 0, 'aktif' => 1, 'urutan' => 3],
                ['nama' => 'Bukti Bebas Administrasi', 'keterangan' => 'Bukti tidak memiliki tunggakan administrasi', 'wajib' => 1, 'is_pembayaran' => 0, 'aktif' => 1, 'urutan' => 4],
                ['nama' => 'Pas Foto', 'keterangan' => 'Pas foto 3x4, latar merah/biru, format JPG/PNG', 'wajib' => 1, 'is_pembayaran' => 0, 'aktif' => 1, 'urutan' => 5],
                ['nama' => 'Dokumen Kelulusan', 'keterangan' => 'SK Yudisium / transkrip nilai akhir', 'wajib' => 1, 'is_pembayaran' => 0, 'aktif' => 1, 'urutan' => 6],
                ['nama' => 'Dokumen Lainnya', 'keterangan' => 'Dokumen tambahan jika diminta kampus (opsional)', 'wajib' => 0, 'is_pembayaran' => 0, 'aktif' => 1, 'urutan' => 7],
            ]);
        }

        $jenis = DB::table('jenis_berkas')->where('aktif', 1)->where('wajib', 1)->orderBy('urutan')->get();

        // 1. Buat Admin
        $adminId = DB::table('users')->insertGetId([
            'email' => 'admin@siwis.test',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'nama' => 'Administrator SIWIS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Buat Periode Wisuda
        $p1Id = DB::table('periode_wisuda')->insertGetId([
            'kode' => '2026A',
            'nama_periode' => 'Wisuda Periode I Tahun 2026',
            'tanggal_wisuda' => '2026-05-23',
            'lokasi' => 'Auditorium Utama Kampus',
            'biaya' => 1500000,
            'min_ipk' => 2.00,
            'status' => 'Ditutup',
            'created_at' => now(),
        ]);

        $p2Id = DB::table('periode_wisuda')->insertGetId([
            'kode' => '2026B',
            'nama_periode' => 'Wisuda Periode II Tahun 2026',
            'tanggal_wisuda' => '2026-12-12',
            'lokasi' => 'Auditorium Utama Kampus',
            'biaya' => 1500000,
            'min_ipk' => 2.00,
            'status' => 'Dibuka',
            'created_at' => now(),
        ]);

        $periodeMap = ['2026A' => $p1Id, '2026B' => $p2Id];

        // 3. Buat Jadwal Wisuda
        $jadwalList = [
            [$p1Id, 'Sesi 1', '2026-05-23', '08.00 - 11.00 WIB', 'Auditorium Utama Kampus', 'Fakultas Teknik dan Hukum'],
            [$p1Id, 'Sesi 2', '2026-05-23', '13.00 - 16.00 WIB', 'Auditorium Utama Kampus', 'Fakultas Ekonomi dan Bisnis serta FKIP'],
            [$p2Id, 'Sesi 1', '2026-12-12', '08.00 - 11.00 WIB', 'Auditorium Utama Kampus', 'Peserta hadir 60 menit sebelum acara dimulai. Pakaian toga lengkap.'],
            [$p2Id, 'Sesi 2', '2026-12-12', '13.00 - 16.00 WIB', 'Auditorium Utama Kampus', 'Peserta hadir 60 menit sebelum acara dimulai. Pakaian toga lengkap.'],
        ];

        foreach ($jadwalList as $j) {
            DB::table('jadwal_wisuda')->insert([
                'periode_id' => $j[0],
                'sesi' => $j[1],
                'tanggal' => $j[2],
                'waktu' => $j[3],
                'lokasi' => $j[4],
                'keterangan' => $j[5],
            ]);
        }

        // 4. Data Mahasiswa
        $mahasiswaData = [
            ['2317020138', 'Rizky Pratama', 'Teknik', 'Teknik Informatika', 3.58, 'Rancang Bangun Sistem Informasi Administrasi Wisudawan Berbasis Web'],
            ['2317020101', 'Aulia Rahmawati', 'Teknik', 'Teknik Informatika', 3.72, 'Implementasi Algoritma K-Means untuk Segmentasi Pelanggan UMKM'],
            ['2317020102', 'Budi Santoso', 'Teknik', 'Sistem Informasi', 3.41, 'Analisis Kepuasan Pengguna Aplikasi Perpustakaan Digital'],
            ['2317020103', 'Citra Dewi', 'Teknik', 'Teknik Sipil', 3.25, 'Evaluasi Kapasitas Drainase Perkotaan Kawasan Padang Timur'],
            ['2317020104', 'Dimas Aditya', 'Ekonomi dan Bisnis', 'Manajemen', 3.10, 'Pengaruh Kualitas Layanan terhadap Loyalitas Pelanggan Toko Daring'],
            ['2317020105', 'Eka Putri Lestari', 'Ekonomi dan Bisnis', 'Akuntansi', 3.88, 'Analisis Penerapan SAK EMKM pada Usaha Mikro Kuliner'],
            ['2317020106', 'Fajar Nugraha', 'Teknik', 'Teknik Informatika', 3.33, 'Sistem Deteksi Plagiarisme Dokumen Menggunakan TF-IDF'],
            ['2317020107', 'Gita Permata', 'Keguruan dan Ilmu Pendidikan', 'PGSD', 3.64, 'Pengembangan Media Ajar Interaktif Tematik Kelas IV'],
            ['2317020108', 'Hendra Wijaya', 'Hukum', 'Ilmu Hukum', 3.05, 'Perlindungan Hukum Konsumen pada Transaksi E-Commerce'],
            ['2317020109', 'Indah Sari', 'Ekonomi dan Bisnis', 'Manajemen', 3.47, 'Strategi Pemasaran Digital pada Usaha Kecil dan Menengah'],
            ['2317020110', 'Joko Susilo', 'Teknik', 'Sistem Informasi', 3.29, 'Perancangan Dashboard Monitoring Penjualan dengan Metode Waterfall'],
            ['2317020111', 'Kartika Ayu', 'Keguruan dan Ilmu Pendidikan', 'Pendidikan Bahasa Inggris', 3.81, 'Efektivitas Project-Based Learning pada Kemampuan Berbicara'],
            ['2317020112', 'Lukman Hakim', 'Hukum', 'Ilmu Hukum', 2.95, 'Penegakan Hukum terhadap Pelanggaran Lalu Lintas Pelajar'],
            ['2317020113', 'Maya Anggraini', 'Ekonomi dan Bisnis', 'Akuntansi', 3.52, 'Pengaruh Pajak Daerah terhadap Pendapatan Asli Daerah'],
            ['2317020114', 'Naufal Ramadhan', 'Teknik', 'Teknik Informatika', 3.18, 'Aplikasi Presensi Mahasiswa Berbasis QR Code'],
            ['2317020115', 'Olivia Hasanah', 'Keguruan dan Ilmu Pendidikan', 'PGSD', 3.39, 'Penerapan Model Problem Based Learning pada Pembelajaran IPA'],
            ['2317020116', 'Putra Mahendra', 'Teknik', 'Teknik Sipil', 3.00, 'Analisis Struktur Gedung Bertingkat Menggunakan SNI Gempa'],
            ['2317020117', 'Qonita Zahra', 'Ekonomi dan Bisnis', 'Manajemen', 3.60, 'Analisis Brand Awareness pada Produk Lokal'],
            ['2317020118', 'Rafi Maulana', 'Teknik', 'Sistem Informasi', 1.95, 'Sistem Informasi Inventori Gudang'],
            ['2317020119', 'Sinta Maharani', 'Hukum', 'Ilmu Hukum', 3.44, 'Kedudukan Hukum Perjanjian Elektronik'],
            ['2317020120', 'Teguh Prasetyo', 'Teknik', 'Teknik Informatika', 3.21, 'Klasifikasi Sentimen Ulasan Aplikasi dengan Naive Bayes'],
            ['2317020121', 'Umi Kalsum', 'Ekonomi dan Bisnis', 'Akuntansi', 3.67, 'Audit Internal Pengelolaan Dana Desa'],
            ['2317020122', 'Vino Saputra', 'Keguruan dan Ilmu Pendidikan', 'Pendidikan Bahasa Inggris', 2.80, null],
        ];

        $skenario = [
            1 => ['2026A', 'Peserta Wisuda', 'Sesi 1'],
            2 => ['2026A', 'Peserta Wisuda', 'Sesi 1'],
            3 => ['2026A', 'Peserta Wisuda', 'Sesi 2'],
            4 => ['2026A', 'Peserta Wisuda', 'Sesi 2'],
            5 => ['2026A', 'Peserta Wisuda', 'Sesi 1'],
            6 => ['2026B', 'Peserta Wisuda', 'Sesi 1'],
            7 => ['2026B', 'Peserta Wisuda', 'Sesi 2'],
            8 => ['2026B', 'Disetujui', null],
            9 => ['2026B', 'Disetujui', null],
            10 => ['2026B', 'Menunggu Verifikasi', null],
            11 => ['2026B', 'Menunggu Verifikasi', null],
            12 => ['2026B', 'Menunggu Verifikasi', null],
            13 => ['2026B', 'Menunggu Verifikasi', null],
            14 => ['2026B', 'Revisi', null],
            15 => ['2026B', 'Revisi', null],
            16 => ['2026B', 'Diverifikasi', null],
            17 => ['2026B', 'Draft', null],
            18 => ['2026B', 'Ditolak', null],
        ];

        $counter = ['2026A' => 0, '2026B' => 0];
        $hMhs = Hash::make('mahasiswa123');
        $fileSeq = 0;

        $pdfContent = "%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 150]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n4 0 obj<</Length 66>>stream\nBT /F1 14 Tf 20 80 Td (Berkas contoh SIWIS - data dummy) Tj ET\nendstream\nendobj\n5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R/Size 6>>\n%%EOF";

        $buatFile = function () use (&$fileSeq, $uploadDir, $pdfContent) {
            $nama = 'seed-' . (++$fileSeq) . '.pdf';
            file_put_contents($uploadDir . '/' . $nama, $pdfContent);
            return $nama;
        };

        foreach ($mahasiswaData as $i => $mhs) {
            [$nim, $nama, $fakultas, $prodi, $ipk, $judul] = $mhs;
            $email = $i === 0 ? 'mahasiswa@siwis.test' : "{$nim}@siwis.test";

            $uId = DB::table('users')->insertGetId([
                'email' => $email,
                'password' => $hMhs,
                'role' => 'mahasiswa',
                'nama' => $nama,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lulus = ($ipk >= 2.0 && $judul && $nim !== '2317020122') ? 'Lulus' : 'Belum Lulus';

            $mId = DB::table('mahasiswa')->insertGetId([
                'user_id' => $uId,
                'nim' => $nim,
                'nama' => $nama,
                'email' => $email,
                'no_hp' => '0812' . substr(strval(30000000 + $i * 1111), 0, 8),
                'fakultas' => $fakultas,
                'program_studi' => $prodi,
                'ipk' => $ipk,
                'judul_skripsi' => $judul,
                'status_kelulusan' => $lulus,
                'tahun_masuk' => 2021,
                'tahun_lulus' => $lulus === 'Lulus' ? 2026 : null,
            ]);

            if (!isset($skenario[$i])) {
                continue;
            }

            [$kodePeriode, $status, $sesi] = $skenario[$i];
            $periodeId = $periodeMap[$kodePeriode];

            $catatan = null;
            if ($status === 'Ditolak') {
                $catatan = 'IPK dan data kelulusan tidak memenuhi persyaratan periode ini.';
            } elseif ($status === 'Revisi') {
                $catatan = 'Mohon perbaiki berkas yang ditandai.';
            }

            $tglDaftar = ($status === 'Draft') ? null : now()->subDays(20 - $i);

            $pdId = DB::table('pendaftaran_wisuda')->insertGetId([
                'mahasiswa_id' => $mId,
                'periode_id' => $periodeId,
                'tanggal_daftar' => $tglDaftar,
                'status' => $status,
                'catatan' => $catatan,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $statusPembayaran = in_array($status, ['Peserta Wisuda', 'Disetujui', 'Diverifikasi']) ? 'Lunas' : ($status === 'Draft' ? 'Belum Bayar' : 'Menunggu Verifikasi');

            DB::table('pembayaran')->insert([
                'pendaftaran_id' => $pdId,
                'nominal' => 1500000,
                'status' => $statusPembayaran,
                'tanggal_pembayaran' => now(),
            ]);

            if ($status !== 'Draft') {
                foreach ($jenis as $k => $j) {
                    $bs = 'Valid';
                    $cat = null;

                    if ($status === 'Menunggu Verifikasi') {
                        $bs = $k < ($i % 4) ? 'Valid' : 'Menunggu Verifikasi';
                    }
                    if ($status === 'Revisi') {
                        if ($k === 4) {
                            $bs = 'Revisi';
                            $cat = 'Pas foto tidak sesuai ketentuan ukuran dan latar. Silakan upload ulang.';
                        } elseif ($k === 1 && $i === 15) {
                            $bs = 'Revisi';
                            $cat = 'Scan surat buram dan tidak terbaca. Mohon upload ulang.';
                        }
                    }
                    if ($status === 'Ditolak') {
                        $bs = ($k === 0) ? 'Ditolak' : 'Menunggu Verifikasi';
                        if ($k === 0) {
                            $cat = 'Bukti pembayaran tidak sesuai nominal.';
                        }
                    }

                    $fileName = strtolower(preg_replace('/\s+/', '-', $j->nama)) . "-{$nim}.pdf";
                    $storedName = $buatFile();

                    DB::table('berkas_wisuda')->insert([
                        'pendaftaran_id' => $pdId,
                        'jenis_berkas_id' => $j->id,
                        'nama_file' => $fileName,
                        'file_path' => $storedName,
                        'status' => $bs,
                        'catatan' => $cat,
                        'tanggal_upload' => now(),
                        'tanggal_verifikasi' => ($bs === 'Menunggu Verifikasi') ? null : now(),
                    ]);
                }
            }

            if ($status === 'Peserta Wisuda') {
                $counter[$kodePeriode]++;
                $nomor = sprintf("WIS-%s-%03d", $kodePeriode, $counter[$kodePeriode]);
                $hadir = ($kodePeriode === '2026A') ? ($i === 4 ? 'Tidak Hadir' : 'Hadir') : 'Belum Hadir';

                DB::table('peserta_wisuda')->insert([
                    'pendaftaran_id' => $pdId,
                    'nomor_peserta' => $nomor,
                    'sesi' => $sesi,
                    'nomor_kursi' => 'A' . $counter[$kodePeriode],
                    'qr_code' => "SIWIS|{$nomor}",
                    'status_kehadiran' => $hadir,
                    'waktu_hadir' => $hadir === 'Hadir' ? '2026-05-23 07:30:00' : null,
                    'created_at' => now(),
                ]);
            }
        }

        DB::table('log_aktivitas')->insert([
            'user_id' => $adminId,
            'aktivitas' => 'Data dummy SIWIS dibuat',
            'created_at' => now(),
        ]);
    }
}
