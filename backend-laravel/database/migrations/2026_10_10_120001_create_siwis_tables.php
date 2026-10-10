<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('nim', 20)->unique();
            $table->string('nama', 120);
            $table->string('email', 120);
            $table->string('no_hp', 20)->nullable();
            $table->string('fakultas', 100);
            $table->string('program_studi', 100);
            $table->decimal('ipk', 3, 2)->default(0);
            $table->string('judul_skripsi', 300)->nullable();
            $table->enum('status_kelulusan', ['Belum Lulus', 'Lulus'])->default('Belum Lulus');
            $table->year('tahun_masuk')->nullable();
            $table->year('tahun_lulus')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('periode_wisuda', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode', 10)->unique();
            $table->string('nama_periode', 120);
            $table->date('tanggal_wisuda');
            $table->string('lokasi', 200);
            $table->decimal('biaya', 12, 2)->default(0);
            $table->decimal('min_ipk', 3, 2)->default(2.00);
            $table->enum('status', ['Dibuka', 'Ditutup'])->default('Dibuka');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('jenis_berkas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama', 120);
            $table->string('keterangan', 255)->nullable();
            $table->boolean('wajib')->default(true);
            $table->boolean('is_pembayaran')->default(false);
            $table->boolean('aktif')->default(true);
            $table->integer('urutan')->default(0);
        });

        Schema::create('pendaftaran_wisuda', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mahasiswa_id');
            $table->unsignedInteger('periode_id');
            $table->dateTime('tanggal_daftar')->nullable();
            $table->enum('status', ['Draft', 'Diajukan', 'Menunggu Verifikasi', 'Revisi', 'Diverifikasi', 'Disetujui', 'Ditolak', 'Peserta Wisuda'])->default('Draft');
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['mahasiswa_id', 'periode_id'], 'uq_mhs_periode');
            $table->foreign('mahasiswa_id')->references('id')->on('mahasiswa')->cascadeOnDelete();
            $table->foreign('periode_id')->references('id')->on('periode_wisuda')->restrictOnDelete();
        });

        Schema::create('berkas_wisuda', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pendaftaran_id');
            $table->unsignedInteger('jenis_berkas_id');
            $table->string('nama_file', 255);
            $table->string('file_path', 255);
            $table->enum('status', ['Belum Upload', 'Menunggu Verifikasi', 'Valid', 'Revisi', 'Ditolak'])->default('Menunggu Verifikasi');
            $table->text('catatan')->nullable();
            $table->dateTime('tanggal_upload');
            $table->dateTime('tanggal_verifikasi')->nullable();
            $table->unique(['pendaftaran_id', 'jenis_berkas_id'], 'uq_daftar_jenis');
            $table->foreign('pendaftaran_id')->references('id')->on('pendaftaran_wisuda')->cascadeOnDelete();
            $table->foreign('jenis_berkas_id')->references('id')->on('jenis_berkas')->restrictOnDelete();
        });

        Schema::create('pembayaran', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pendaftaran_id')->unique();
            $table->decimal('nominal', 12, 2)->default(0);
            $table->string('bukti_pembayaran', 255)->nullable();
            $table->enum('status', ['Belum Bayar', 'Menunggu Verifikasi', 'Lunas', 'Ditolak'])->default('Belum Bayar');
            $table->dateTime('tanggal_pembayaran')->nullable();
            $table->foreign('pendaftaran_id')->references('id')->on('pendaftaran_wisuda')->cascadeOnDelete();
        });

        Schema::create('peserta_wisuda', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pendaftaran_id')->unique();
            $table->string('nomor_peserta', 30)->unique();
            $table->string('sesi', 30)->nullable();
            $table->string('nomor_kursi', 20)->nullable();
            $table->string('qr_code', 120);
            $table->enum('status_kehadiran', ['Belum Hadir', 'Hadir', 'Tidak Hadir'])->default('Belum Hadir');
            $table->dateTime('waktu_hadir')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('pendaftaran_id')->references('id')->on('pendaftaran_wisuda')->cascadeOnDelete();
        });

        Schema::create('jadwal_wisuda', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('periode_id');
            $table->string('sesi', 30);
            $table->date('tanggal');
            $table->string('waktu', 30);
            $table->string('lokasi', 200);
            $table->string('keterangan', 300)->nullable();
            $table->foreign('periode_id')->references('id')->on('periode_wisuda')->cascadeOnDelete();
        });

        Schema::create('notifikasi', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('user_id');
            $table->string('pesan', 300);
            $table->boolean('dibaca')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('aktivitas', 300);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

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

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
        Schema::dropIfExists('notifikasi');
        Schema::dropIfExists('jadwal_wisuda');
        Schema::dropIfExists('peserta_wisuda');
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('berkas_wisuda');
        Schema::dropIfExists('pendaftaran_wisuda');
        Schema::dropIfExists('jenis_berkas');
        Schema::dropIfExists('periode_wisuda');
        Schema::dropIfExists('mahasiswa');
    }
};