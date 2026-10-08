<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Health Check
Route::get('/health', function () {
    try {
        DB::select('SELECT 1');
        return response()->json(['status' => 'ok', 'database' => 'terhubung']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'database' => $e->getMessage()], 500);
    }
});

// Authentication (Public)
Route::get('/login', fn () => response()->json(['message' => 'Unauthenticated.'], 401))->name('login');
Route::post('/auth/login', [AuthController::class, 'login']);

// Authenticated Routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth Profile & Password
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);
    Route::put('/auth/profil', [AuthController::class, 'updateProfile']);

    // Student Routes
    Route::middleware('role:mahasiswa')->prefix('student')->group(function () {
        Route::get('/dashboard', [StudentController::class, 'dashboard']);
        Route::get('/profil', [StudentController::class, 'getProfile']);
        Route::put('/profil', [StudentController::class, 'updateProfile']);
        Route::get('/periode', [StudentController::class, 'getPeriode']);
        Route::get('/pendaftaran', [StudentController::class, 'getPendaftaran']);
        Route::post('/pendaftaran', [StudentController::class, 'storePendaftaran']);
        Route::post('/pendaftaran/submit', [StudentController::class, 'submitPendaftaran']);
        Route::post('/berkas', [StudentController::class, 'uploadBerkas']);
        Route::get('/berkas/{id}/file', [StudentController::class, 'downloadBerkas']);
        Route::get('/jadwal', [StudentController::class, 'getJadwal']);
        Route::get('/kartu', [StudentController::class, 'getKartu']);
        Route::get('/kartu/pdf', [StudentController::class, 'downloadKartuPdf']);
        Route::get('/notifikasi', [StudentController::class, 'getNotifikasi']);
        Route::post('/notifikasi/baca', [StudentController::class, 'bacaNotifikasi']);
    });

    // Admin Routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/opsi', [AdminController::class, 'opsi']);

        Route::get('/periode', [AdminController::class, 'indexPeriode']);
        Route::post('/periode', [AdminController::class, 'storePeriode']);
        Route::put('/periode/{id}', [AdminController::class, 'updatePeriode']);
        Route::delete('/periode/{id}', [AdminController::class, 'destroyPeriode']);

        Route::get('/pendaftar', [AdminController::class, 'indexPendaftar']);
        Route::get('/pendaftar/{id}', [AdminController::class, 'showPendaftar']);
        Route::get('/berkas/{id}/file', [AdminController::class, 'downloadBerkas']);
        Route::put('/berkas/{id}', [AdminController::class, 'verifikasiBerkas']);
        Route::post('/pendaftar/{id}/keputusan', [AdminController::class, 'keputusan']);

        Route::get('/peserta', [AdminController::class, 'indexPeserta']);
        Route::post('/peserta', [AdminController::class, 'storePeserta']);
        Route::post('/peserta/massal', [AdminController::class, 'storePesertaMassal']);
        Route::put('/peserta/{id}', [AdminController::class, 'updatePeserta']);
        Route::delete('/peserta/{id}', [AdminController::class, 'destroyPeserta']);

        Route::get('/jadwal', [AdminController::class, 'indexJadwal']);
        Route::post('/jadwal', [AdminController::class, 'storeJadwal']);
        Route::put('/jadwal/{id}', [AdminController::class, 'updateJadwal']);
        Route::delete('/jadwal/{id}', [AdminController::class, 'destroyJadwal']);

        Route::get('/kehadiran', [AdminController::class, 'indexKehadiran']);
        Route::post('/kehadiran/scan', [AdminController::class, 'scanKehadiran']);
        Route::put('/kehadiran/{id}', [AdminController::class, 'updateKehadiran']);

        Route::get('/laporan', [AdminController::class, 'laporan']);

        Route::get('/jenis-berkas', [AdminController::class, 'indexJenisBerkas']);
        Route::post('/jenis-berkas', [AdminController::class, 'storeJenisBerkas']);
        Route::put('/jenis-berkas/{id}', [AdminController::class, 'updateJenisBerkas']);
        Route::delete('/jenis-berkas/{id}', [AdminController::class, 'destroyJenisBerkas']);

        Route::get('/mahasiswa', [AdminController::class, 'indexMahasiswa']);
        Route::post('/mahasiswa', [AdminController::class, 'storeMahasiswa']);
        Route::put('/mahasiswa/{id}', [AdminController::class, 'updateMahasiswa']);
        Route::delete('/mahasiswa/{id}', [AdminController::class, 'destroyMahasiswa']);

        Route::get('/notifikasi', [AdminController::class, 'getNotifikasi']);
        Route::post('/notifikasi/baca', [AdminController::class, 'bacaNotifikasi']);
        Route::get('/qr/{nomor}', [AdminController::class, 'getQr']);
    });

    // Export Routes (Admin Only)
    Route::middleware('role:admin')->prefix('export')->group(function () {
        Route::get('/rekap', [ExportController::class, 'rekap']);
        Route::get('/laporan-pdf', [ExportController::class, 'laporanPdf']);
        Route::get('/{tipe}', [ExportController::class, 'exportTipe']);
    });
});
