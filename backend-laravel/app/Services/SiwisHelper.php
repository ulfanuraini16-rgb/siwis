<?php

namespace App\Services;

use App\Models\LogAktivitas;
use App\Models\Notifikasi;
use App\Models\User;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\DB;

class SiwisHelper
{
    public static function notify(int $userId, string $pesan): void
    {
        Notifikasi::create([
            'user_id' => $userId,
            'pesan' => $pesan,
            'dibaca' => false,
        ]);
    }

    public static function notifAdmins(string $pesan): void
    {
        $adminIds = User::where('role', 'admin')->pluck('id');
        foreach ($adminIds as $id) {
            self::notify($id, $pesan);
        }
    }

    public static function logActivity(?int $userId, string $aktivitas): void
    {
        LogAktivitas::create([
            'user_id' => $userId,
            'aktivitas' => $aktivitas,
        ]);
    }

    public static function userIdOfPendaftaran(int $pendaftaranId): ?int
    {
        $row = DB::table('pendaftaran_wisuda as p')
            ->join('mahasiswa as m', 'm.id', '=', 'p.mahasiswa_id')
            ->where('p.id', $pendaftaranId)
            ->select('m.user_id')
            ->first();

        return $row ? (int) $row->user_id : null;
    }

    public static function getPendaftaranDetail(int $id): ?object
    {
        $p = DB::table('pendaftaran_wisuda as p')
            ->join('mahasiswa as m', 'm.id', '=', 'p.mahasiswa_id')
            ->join('periode_wisuda as pr', 'pr.id', '=', 'p.periode_id')
            ->leftJoin('peserta_wisuda as ps', 'ps.pendaftaran_id', '=', 'p.id')
            ->leftJoin('pembayaran as pb', 'pb.pendaftaran_id', '=', 'p.id')
            ->where('p.id', $id)
            ->select(
                'p.*',
                'm.nim',
                'm.nama',
                'm.email',
                'm.no_hp',
                'm.fakultas',
                'm.program_studi',
                'm.ipk',
                'm.judul_skripsi',
                'm.status_kelulusan',
                'm.tahun_masuk',
                'm.tahun_lulus',
                'm.user_id',
                'pr.nama_periode',
                'pr.kode as kode_periode',
                'pr.tanggal_wisuda',
                'pr.lokasi as lokasi_periode',
                'pr.biaya',
                'pr.min_ipk',
                'ps.id as peserta_id',
                'ps.nomor_peserta',
                'ps.sesi',
                'ps.nomor_kursi',
                'ps.status_kehadiran',
                'ps.waktu_hadir',
                'pb.status as status_pembayaran',
                'pb.nominal'
            )
            ->first();

        if (!$p) {
            return null;
        }

        $berkas = DB::table('jenis_berkas as jb')
            ->leftJoin('berkas_wisuda as b', function ($join) use ($id) {
                $join->on('b.jenis_berkas_id', '=', 'jb.id')
                    ->where('b.pendaftaran_id', '=', $id);
            })
            ->where(function ($query) {
                $query->where('jb.aktif', 1)
                    ->orWhereNotNull('b.id');
            })
            ->select(
                'jb.id as jenis_id',
                'jb.nama as jenis_nama',
                'jb.keterangan',
                'jb.wajib',
                'jb.is_pembayaran',
                'b.id as berkas_id',
                'b.nama_file',
                'b.status',
                'b.catatan',
                'b.tanggal_upload',
                'b.tanggal_verifikasi'
            )
            ->orderBy('jb.urutan')
            ->orderBy('jb.id')
            ->get();

        $berkasList = [];
        $wajibTotal = 0;
        $wajibValid = 0;
        $wajibTerupload = 0;

        foreach ($berkas as $b) {
            $item = (object) (array) $b;
            $item->status = $item->status ?? 'Belum Upload';
            $berkasList[] = $item;

            if ($item->wajib) {
                $wajibTotal++;
                if ($item->status === 'Valid') {
                    $wajibValid++;
                }
                if ($item->status !== 'Belum Upload') {
                    $wajibTerupload++;
                }
            }
        }

        $p->berkas = $berkasList;
        $p->berkas_wajib_total = $wajibTotal;
        $p->berkas_wajib_valid = $wajibValid;
        $p->berkas_wajib_terupload = $wajibTerupload;

        return $p;
    }

    public static function buildWhere(array $q = [], array $opts = []): array
    {
        $where = [];
        $params = [];

        if (!empty($opts['exclude_draft'])) {
            $where[] = "p.status <> 'Draft'";
        }

        if (!empty($q['periode_id'])) {
            $where[] = 'p.periode_id = ?';
            $params[] = (int) $q['periode_id'];
        }

        if (!empty($q['fakultas'])) {
            $where[] = 'm.fakultas = ?';
            $params[] = $q['fakultas'];
        }

        if (!empty($q['program_studi'])) {
            $where[] = 'm.program_studi = ?';
            $params[] = $q['program_studi'];
        }

        if (!empty($q['status'])) {
            $where[] = 'p.status = ?';
            $params[] = $q['status'];
        }

        if (!empty($q['sesi'])) {
            $where[] = 'ps.sesi = ?';
            $params[] = $q['sesi'];
        }

        if (!empty($q['status_kehadiran'])) {
            $where[] = 'ps.status_kehadiran = ?';
            $params[] = $q['status_kehadiran'];
        }

        if (isset($q['status_peserta'])) {
            if ($q['status_peserta'] === 'Peserta') {
                $where[] = 'ps.id IS NOT NULL';
            } elseif ($q['status_peserta'] === 'Bukan Peserta') {
                $where[] = 'ps.id IS NULL';
            }
        }

        if (!empty($q['status_berkas'])) {
            if ($q['status_berkas'] === 'Belum Upload') {
                $where[] = "(SELECT COUNT(*) FROM berkas_wisuda b JOIN jenis_berkas j ON j.id = b.jenis_berkas_id WHERE b.pendaftaran_id = p.id AND j.wajib = 1)
                            < (SELECT COUNT(*) FROM jenis_berkas WHERE aktif = 1 AND wajib = 1)";
            } else {
                $where[] = 'EXISTS (SELECT 1 FROM berkas_wisuda b WHERE b.pendaftaran_id = p.id AND b.status = ?)';
                $params[] = $q['status_berkas'];
            }
        }

        if (!empty($q['q'])) {
            $where[] = '(m.nim LIKE ? OR m.nama LIKE ?)';
            $search = '%' . $q['q'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $sql = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

        return ['sql' => $sql, 'params' => $params];
    }

    public static function queryPendaftar(array $q = [], array $opts = []): array
    {
        $base = "
            SELECT p.id, p.status, p.tanggal_daftar, p.catatan, p.periode_id,
                   m.nim, m.nama, m.fakultas, m.program_studi, m.ipk, m.email, m.no_hp, m.judul_skripsi,
                   pr.nama_periode, pr.kode AS kode_periode,
                   ps.id AS peserta_id, ps.nomor_peserta, ps.sesi, ps.nomor_kursi, ps.status_kehadiran, ps.waktu_hadir,
                   (SELECT COUNT(*) FROM berkas_wisuda b WHERE b.pendaftaran_id = p.id AND b.status = 'Valid') AS berkas_valid,
                   (SELECT COUNT(*) FROM berkas_wisuda b WHERE b.pendaftaran_id = p.id) AS berkas_upload,
                   (SELECT COUNT(*) FROM jenis_berkas WHERE aktif = 1 AND wajib = 1) AS berkas_wajib
            FROM pendaftaran_wisuda p
            JOIN mahasiswa m ON m.id = p.mahasiswa_id
            JOIN periode_wisuda pr ON pr.id = p.periode_id
            LEFT JOIN peserta_wisuda ps ON ps.pendaftaran_id = p.id";

        $filter = self::buildWhere($q, $opts);
        $sql = $base . $filter['sql'] . ' ORDER BY pr.tanggal_wisuda DESC, m.fakultas, m.program_studi, m.nim LIMIT 2000';

        $rows = DB::select($sql, $filter['params']);

        return array_map(function ($r) {
            $r->ipk = (float) $r->ipk;
            $r->berkas_valid = (int) $r->berkas_valid;
            $r->berkas_upload = (int) $r->berkas_upload;
            $r->berkas_wajib = (int) $r->berkas_wajib;
            return $r;
        }, $rows);
    }

    public static function qrPayload(string $nomor): string
    {
        return 'SIWIS|' . $nomor;
    }

    public static function qrDataUrl(string $nomor): string
    {
        $options = new QROptions;
        $options->outputInterface = QRGdImagePNG::class;
        $options->outputBase64 = true;
        $options->scale = 6;
        $options->imageTransparent = false;

        $qrcode = new QRCode($options);
        return $qrcode->render(self::qrPayload($nomor));
    }

    public static function qrPngBinary(string $nomor): string
    {
        $options = new QROptions;
        $options->outputInterface = QRGdImagePNG::class;
        $options->outputBase64 = false;
        $options->scale = 6;
        $options->imageTransparent = false;

        $qrcode = new QRCode($options);
        return $qrcode->render(self::qrPayload($nomor));
    }

    public static function parseKode(?string $kode): string
    {
        $s = trim((string) $kode);
        $s = preg_replace('/^SIWIS\|/i', '', $s);
        return strtoupper($s);
    }

    public static function getUploadDirectory(): string
    {
        $dir = storage_path('app/uploads');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public static function findFilePath(string $filename): ?string
    {
        $primary = storage_path('app/uploads/' . $filename);
        if (file_exists($primary)) {
            return $primary;
        }

        // Fallback to legacy backend uploads if existed
        $fallback = base_path('../backend/uploads/' . $filename);
        if (file_exists($fallback)) {
            return $fallback;
        }

        return null;
    }
}
