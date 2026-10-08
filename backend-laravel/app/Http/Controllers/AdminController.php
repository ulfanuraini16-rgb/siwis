<?php

namespace App\Http\Controllers;

use App\Models\BerkasWisuda;
use App\Models\JadwalWisuda;
use App\Models\JenisBerkas;
use App\Models\Mahasiswa;
use App\Models\Notifikasi;
use App\Models\PendaftaranWisuda;
use App\Models\PeriodeWisuda;
use App\Models\PesertaWisuda;
use App\Models\User;
use App\Services\SiwisHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $periodeId = $request->query('periode_id');
        $pf = $periodeId ? 'WHERE p.periode_id = ?' : '';
        $pp = $periodeId ? [(int) $periodeId] : [];

        $cardQuery = "
            SELECT COUNT(CASE WHEN p.status <> 'Draft' THEN 1 END) AS total_pendaftar,
                   SUM(p.status = 'Menunggu Verifikasi') AS menunggu,
                   SUM(p.status = 'Revisi') AS revisi,
                   SUM(p.status IN ('Disetujui','Peserta Wisuda')) AS disetujui,
                   SUM(p.status = 'Peserta Wisuda') AS peserta,
                   SUM(ps.status_kehadiran = 'Hadir') AS hadir,
                   SUM(ps.status_kehadiran = 'Tidak Hadir') AS tidak_hadir
            FROM pendaftaran_wisuda p
            LEFT JOIN peserta_wisuda ps ON ps.pendaftaran_id = p.id
            {$pf}
        ";
        $card = DB::selectOne($cardQuery, $pp);

        $fakWhere = $periodeId ? "WHERE p.periode_id = ? AND p.status <> 'Draft'" : "WHERE p.status <> 'Draft'";
        $perFakultas = DB::select("
            SELECT m.fakultas AS label, COUNT(*) AS jumlah
            FROM pendaftaran_wisuda p
            JOIN mahasiswa m ON m.id = p.mahasiswa_id
            {$fakWhere}
            GROUP BY m.fakultas
            ORDER BY jumlah DESC
        ", $pp);

        $perProdi = DB::select("
            SELECT m.program_studi AS label, COUNT(*) AS jumlah
            FROM pendaftaran_wisuda p
            JOIN mahasiswa m ON m.id = p.mahasiswa_id
            {$fakWhere}
            GROUP BY m.program_studi
            ORDER BY jumlah DESC
            LIMIT 8
        ", $pp);

        $perStatus = DB::select("
            SELECT p.status AS label, COUNT(*) AS jumlah
            FROM pendaftaran_wisuda p
            {$pf}
            GROUP BY p.status
        ", $pp);

        $perPeriode = DB::select("
            SELECT pr.nama_periode AS label, COUNT(p.id) AS jumlah
            FROM periode_wisuda pr
            LEFT JOIN pendaftaran_wisuda p ON p.periode_id = pr.id AND p.status <> 'Draft'
            GROUP BY pr.id, pr.nama_periode, pr.tanggal_wisuda
            ORDER BY pr.tanggal_wisuda
        ");

        $terbaruAll = SiwisHelper::queryPendaftar(['periode_id' => $periodeId], ['exclude_draft' => true]);
        usort($terbaruAll, fn ($a, $b) => strcmp((string) $b->tanggal_daftar, (string) $a->tanggal_daftar));
        $terbaru = array_slice($terbaruAll, 0, 6);

        $aktivitas = DB::select("
            SELECT l.aktivitas, l.created_at, u.nama
            FROM log_aktivitas l
            LEFT JOIN users u ON u.id = l.user_id
            ORDER BY l.id DESC
            LIMIT 8
        ");

        $kartuData = [];
        foreach ((array) $card as $k => $v) {
            $kartuData[$k] = (int) ($v ?? 0);
        }

        return response()->json([
            'kartu' => $kartuData,
            'perFakultas' => $perFakultas,
            'perProdi' => $perProdi,
            'perStatus' => $perStatus,
            'perPeriode' => $perPeriode,
            'terbaru' => $terbaru,
            'aktivitas' => $aktivitas,
        ]);
    }

    public function opsi(): JsonResponse
    {
        $periode = PeriodeWisuda::select('id', 'kode', 'nama_periode', 'status')
            ->orderBy('tanggal_wisuda', 'desc')
            ->get();

        $fakultas = Mahasiswa::distinct()->orderBy('fakultas')->pluck('fakultas');

        $prodi = Mahasiswa::select('fakultas', 'program_studi')
            ->distinct()
            ->orderBy('program_studi')
            ->get();

        $sesi = PesertaWisuda::whereNotNull('sesi')
            ->distinct()
            ->orderBy('sesi')
            ->pluck('sesi');

        return response()->json([
            'periode' => $periode,
            'fakultas' => $fakultas,
            'prodi' => $prodi,
            'sesi' => $sesi,
        ]);
    }

    // ---------- Periode ----------
    public function indexPeriode(): JsonResponse
    {
        $rows = DB::select("
            SELECT pr.*, (SELECT COUNT(*) FROM pendaftaran_wisuda p WHERE p.periode_id = pr.id) AS jumlah_pendaftar
            FROM periode_wisuda pr
            ORDER BY pr.tanggal_wisuda DESC
        ");

        return response()->json($rows);
    }

    private function validatePeriode(Request $request): ?string
    {
        $kode = trim((string) $request->input('kode', ''));
        if (!$kode || !preg_match('/^[A-Za-z0-9]{2,10}$/', $kode)) {
            return 'Kode periode wajib diisi (2-10 huruf/angka, contoh 2026A).';
        }
        if (!trim((string) $request->input('nama_periode', ''))) {
            return 'Nama periode wajib diisi.';
        }
        if (!$request->input('tanggal_wisuda')) {
            return 'Tanggal wisuda wajib diisi.';
        }
        if (!trim((string) $request->input('lokasi', ''))) {
            return 'Lokasi wajib diisi.';
        }
        $biaya = $request->input('biaya', 0);
        if (!is_numeric($biaya) || $biaya < 0) {
            return 'Biaya tidak valid.';
        }
        $ipk = $request->input('min_ipk', 2.0);
        if (!is_numeric($ipk) || $ipk < 0 || $ipk > 4) {
            return 'IPK minimal harus antara 0 - 4.';
        }
        if (!in_array($request->input('status'), ['Dibuka', 'Ditutup'])) {
            return 'Status periode tidak valid.';
        }
        return null;
    }

    public function storePeriode(Request $request): JsonResponse
    {
        $err = $this->validatePeriode($request);
        if ($err) return response()->json(['message' => $err], 400);

        $kode = strtoupper(trim((string) $request->input('kode')));
        if (PeriodeWisuda::where('kode', $kode)->exists()) {
            return response()->json(['message' => 'Kode periode sudah digunakan.'], 400);
        }

        $namaPeriode = trim((string) $request->input('nama_periode'));
        PeriodeWisuda::create([
            'kode' => $kode,
            'nama_periode' => $namaPeriode,
            'tanggal_wisuda' => $request->input('tanggal_wisuda'),
            'lokasi' => trim((string) $request->input('lokasi')),
            'biaya' => (float) $request->input('biaya', 0),
            'min_ipk' => (float) $request->input('min_ipk', 2.0),
            'status' => $request->input('status'),
        ]);

        SiwisHelper::logActivity($request->user()->id, "Admin membuat periode {$namaPeriode}");

        return response()->json(['message' => 'Periode wisuda berhasil dibuat.'], 201);
    }

    public function updatePeriode(Request $request, int $id): JsonResponse
    {
        $err = $this->validatePeriode($request);
        if ($err) return response()->json(['message' => $err], 400);

        $periode = PeriodeWisuda::find($id);
        if (!$periode) {
            return response()->json(['message' => 'Periode wisuda tidak ditemukan.'], 404);
        }

        $kode = strtoupper(trim((string) $request->input('kode')));
        $duplicate = PeriodeWisuda::where('kode', $kode)->where('id', '<>', $id)->exists();
        if ($duplicate) {
            return response()->json(['message' => 'Kode periode sudah digunakan.'], 400);
        }

        $namaPeriode = trim((string) $request->input('nama_periode'));
        $periode->update([
            'kode' => $kode,
            'nama_periode' => $namaPeriode,
            'tanggal_wisuda' => $request->input('tanggal_wisuda'),
            'lokasi' => trim((string) $request->input('lokasi')),
            'biaya' => (float) $request->input('biaya', 0),
            'min_ipk' => (float) $request->input('min_ipk', 2.0),
            'status' => $request->input('status'),
        ]);

        SiwisHelper::logActivity($request->user()->id, "Admin memperbarui periode {$namaPeriode}");

        return response()->json(['message' => 'Periode wisuda berhasil diperbarui.']);
    }

    public function destroyPeriode(Request $request, int $id): JsonResponse
    {
        $pendaftarCount = PendaftaranWisuda::where('periode_id', $id)->count();
        if ($pendaftarCount > 0) {
            return response()->json(['message' => 'Periode tidak dapat dihapus karena sudah memiliki pendaftar. Ubah statusnya menjadi Ditutup.'], 400);
        }

        PeriodeWisuda::where('id', $id)->delete();
        SiwisHelper::logActivity($request->user()->id, 'Admin menghapus periode wisuda');

        return response()->json(['message' => 'Periode wisuda dihapus.']);
    }

    // ---------- Pendaftar ----------
    public function indexPendaftar(Request $request): JsonResponse
    {
        $excludeDraft = $request->query('termasuk_draft') !== '1';
        $rows = SiwisHelper::queryPendaftar($request->query(), ['exclude_draft' => $excludeDraft]);
        return response()->json($rows);
    }

    public function showPendaftar(int $id): JsonResponse
    {
        $d = SiwisHelper::getPendaftaranDetail($id);
        if (!$d) {
            return response()->json(['message' => 'Pendaftaran tidak ditemukan.'], 404);
        }
        return response()->json($d);
    }

    public function downloadBerkas(int $id): BinaryFileResponse|JsonResponse
    {
        $berkas = BerkasWisuda::find($id);
        if (!$berkas) {
            return response()->json(['message' => 'Berkas tidak ditemukan.'], 404);
        }

        $path = SiwisHelper::findFilePath($berkas->file_path);
        if (!$path) {
            return response()->json(['message' => 'File fisik tidak ditemukan pada server.'], 404);
        }

        return response()->download($path, $berkas->nama_file);
    }

    public function verifikasiBerkas(Request $request, int $id): JsonResponse
    {
        $status = (string) $request->input('status');
        $catatan = trim((string) $request->input('catatan', ''));

        if (!in_array($status, ['Valid', 'Revisi', 'Ditolak'])) {
            return response()->json(['message' => 'Status berkas tidak valid.'], 400);
        }

        if ($status !== 'Valid' && $catatan === '') {
            return response()->json(['message' => 'Alasan wajib diisi untuk status Revisi atau Ditolak.'], 400);
        }

        $berkasRow = DB::table('berkas_wisuda as b')
            ->join('jenis_berkas as jb', 'jb.id', '=', 'b.jenis_berkas_id')
            ->join('pendaftaran_wisuda as p', 'p.id', '=', 'b.pendaftaran_id')
            ->where('b.id', $id)
            ->select('b.*', 'jb.nama as jenis_nama', 'jb.is_pembayaran', 'p.status as status_daftar')
            ->first();

        if (!$berkasRow) {
            return response()->json(['message' => 'Berkas tidak ditemukan.'], 404);
        }

        if (!in_array($berkasRow->status_daftar, ['Menunggu Verifikasi', 'Revisi', 'Diverifikasi'])) {
            return response()->json(['message' => "Berkas tidak dapat diverifikasi pada status pendaftaran \"{$berkasRow->status_daftar}\"."], 400);
        }

        DB::table('berkas_wisuda')->where('id', $id)->update([
            'status' => $status,
            'catatan' => $status === 'Valid' ? null : $catatan,
            'tanggal_verifikasi' => now(),
        ]);

        if ($berkasRow->is_pembayaran) {
            $sp = $status === 'Valid' ? 'Lunas' : 'Ditolak';
            DB::table('pembayaran')->where('pendaftaran_id', $berkasRow->pendaftaran_id)->update(['status' => $sp]);
        }

        $uid = SiwisHelper::userIdOfPendaftaran($berkasRow->pendaftaran_id);
        if ($uid) {
            if ($status === 'Valid') {
                SiwisHelper::notify($uid, "Berkas {$berkasRow->jenis_nama} Anda dinyatakan valid.");
            } else {
                SiwisHelper::notify($uid, "Berkas {$berkasRow->jenis_nama} perlu diperbaiki: {$catatan}");
            }
        }

        // Sinkronkan status pendaftaran
        $d = SiwisHelper::getPendaftaranDetail($berkasRow->pendaftaran_id);
        $bermasalah = false;
        foreach ($d->berkas as $x) {
            if (in_array($x->status, ['Revisi', 'Ditolak'])) {
                $bermasalah = true;
                break;
            }
        }

        $statusBaru = $d->status;
        if ($bermasalah) {
            $statusBaru = 'Revisi';
        } elseif ($d->berkas_wajib_valid === $d->berkas_wajib_total && collect($d->berkas)->every(fn ($x) => in_array($x->status, ['Valid', 'Belum Upload']))) {
            $statusBaru = 'Diverifikasi';
        } else {
            $statusBaru = 'Menunggu Verifikasi';
        }

        if ($statusBaru !== $d->status) {
            DB::table('pendaftaran_wisuda')->where('id', $d->id)->update([
                'status' => $statusBaru,
                'updated_at' => now(),
            ]);

            if ($statusBaru === 'Diverifikasi' && $uid) {
                SiwisHelper::notify($uid, 'Seluruh berkas Anda telah diverifikasi. Menunggu persetujuan akhir Admin.');
            }
        }

        SiwisHelper::logActivity($request->user()->id, "Admin memberi status {$status} pada berkas {$berkasRow->jenis_nama} ({$d->nim})");

        return response()->json([
            'message' => "Berkas ditandai {$status}.",
            'status_pendaftaran' => $statusBaru,
        ]);
    }

    public function keputusan(Request $request, int $id): JsonResponse
    {
        $aksi = (string) $request->input('aksi');
        $catatan = trim((string) $request->input('catatan', ''));

        $d = SiwisHelper::getPendaftaranDetail($id);
        if (!$d) {
            return response()->json(['message' => 'Pendaftaran tidak ditemukan.'], 404);
        }

        if (in_array($d->status, ['Draft', 'Peserta Wisuda'])) {
            return response()->json(['message' => "Keputusan tidak dapat diberikan pada status \"{$d->status}\"."], 400);
        }

        $uid = $d->user_id;

        if ($aksi === 'setujui') {
            if (in_array($d->status, ['Disetujui', 'Ditolak'])) {
                return response()->json(['message' => "Pendaftaran sudah berstatus {$d->status}."], 400);
            }

            $hasProblem = false;
            foreach ($d->berkas as $x) {
                if (in_array($x->status, ['Revisi', 'Ditolak'])) {
                    $hasProblem = true;
                    break;
                }
            }

            if ($d->berkas_wajib_valid < $d->berkas_wajib_total || $hasProblem) {
                return response()->json(['message' => 'Tidak dapat menyetujui. Semua berkas wajib harus berstatus Valid terlebih dahulu.'], 400);
            }

            if ((float) $d->ipk < (float) $d->min_ipk || $d->status_kelulusan !== 'Lulus') {
                return response()->json(['message' => 'Mahasiswa belum memenuhi syarat akademik (kelulusan/IPK).'], 400);
            }

            DB::table('pendaftaran_wisuda')->where('id', $d->id)->update([
                'status' => 'Disetujui',
                'catatan' => $catatan ?: null,
                'updated_at' => now(),
            ]);

            if ($uid) SiwisHelper::notify($uid, 'Pendaftaran wisuda Anda telah disetujui.');
            SiwisHelper::logActivity($request->user()->id, "Admin menyetujui pendaftaran {$d->nama} ({$d->nim})");

            return response()->json(['message' => 'Pendaftaran disetujui.']);
        }

        if ($aksi === 'revisi') {
            if ($catatan === '') {
                return response()->json(['message' => 'Catatan revisi wajib diisi.'], 400);
            }
            if (in_array($d->status, ['Disetujui', 'Ditolak'])) {
                return response()->json(['message' => "Pendaftaran sudah berstatus {$d->status}."], 400);
            }

            DB::table('pendaftaran_wisuda')->where('id', $d->id)->update([
                'status' => 'Revisi',
                'catatan' => $catatan,
                'updated_at' => now(),
            ]);

            if ($uid) SiwisHelper::notify($uid, "Pendaftaran wisuda Anda perlu direvisi: {$catatan}");
            SiwisHelper::logActivity($request->user()->id, "Admin meminta revisi pendaftaran {$d->nama} ({$d->nim})");

            return response()->json(['message' => 'Pendaftaran dikembalikan untuk revisi.']);
        }

        if ($aksi === 'tolak') {
            if ($catatan === '') {
                return response()->json(['message' => 'Alasan penolakan wajib diisi.'], 400);
            }
            if ($d->status === 'Disetujui') {
                return response()->json(['message' => 'Pendaftaran yang sudah disetujui tidak dapat ditolak.'], 400);
            }

            DB::table('pendaftaran_wisuda')->where('id', $d->id)->update([
                'status' => 'Ditolak',
                'catatan' => $catatan,
                'updated_at' => now(),
            ]);

            if ($uid) SiwisHelper::notify($uid, "Pendaftaran wisuda Anda ditolak: {$catatan}");
            SiwisHelper::logActivity($request->user()->id, "Admin menolak pendaftaran {$d->nama} ({$d->nim})");

            return response()->json(['message' => 'Pendaftaran ditolak.']);
        }

        return response()->json(['message' => 'Aksi tidak dikenal.'], 400);
    }

    // ---------- Peserta ----------
    private function tetapkanPeserta(int $pendaftaranId, ?string $sesi, ?string $nomorKursi): array
    {
        $p = DB::table('pendaftaran_wisuda as p')
            ->join('periode_wisuda as pr', 'pr.id', '=', 'p.periode_id')
            ->join('mahasiswa as m', 'm.id', '=', 'p.mahasiswa_id')
            ->where('p.id', $pendaftaranId)
            ->select('p.id', 'p.status', 'p.periode_id', 'pr.kode', 'm.user_id', 'm.nama')
            ->lockForUpdate()
            ->first();

        if (!$p) {
            throw new \Exception('Pendaftaran tidak ditemukan.', 404);
        }

        if ($p->status !== 'Disetujui') {
            throw new \Exception('Hanya mahasiswa berstatus Disetujui yang dapat ditetapkan sebagai peserta.', 400);
        }

        $last = DB::selectOne("
            SELECT MAX(CAST(SUBSTRING_INDEX(ps.nomor_peserta, '-', -1) AS UNSIGNED)) AS maks
            FROM peserta_wisuda ps
            JOIN pendaftaran_wisuda x ON x.id = ps.pendaftaran_id
            WHERE x.periode_id = ?
        ", [$p->periode_id]);

        $nextSeq = (int) ($last->maks ?? 0) + 1;
        $nomor = sprintf("WIS-%s-%03d", $p->kode, $nextSeq);

        DB::table('peserta_wisuda')->insert([
            'pendaftaran_id' => $p->id,
            'nomor_peserta' => $nomor,
            'sesi' => $sesi ?: null,
            'nomor_kursi' => $nomorKursi ?: null,
            'qr_code' => "SIWIS|{$nomor}",
            'status_kehadiran' => 'Belum Hadir',
            'created_at' => now(),
        ]);

        DB::table('pendaftaran_wisuda')->where('id', $p->id)->update([
            'status' => 'Peserta Wisuda',
            'updated_at' => now(),
        ]);

        if ($p->user_id) {
            SiwisHelper::notify($p->user_id, "Selamat, Anda telah ditetapkan sebagai peserta wisuda dengan nomor {$nomor}.");
        }

        return ['nomor' => $nomor, 'nama' => $p->nama];
    }

    public function indexPeserta(Request $request): JsonResponse
    {
        $rows = SiwisHelper::queryPendaftar(array_merge($request->query(), ['status_peserta' => 'Peserta']));
        return response()->json($rows);
    }

    public function storePeserta(Request $request): JsonResponse
    {
        $pId = (int) $request->input('pendaftaran_id');
        $sesi = $request->input('sesi');
        $nomorKursi = $request->input('nomor_kursi');

        try {
            $result = DB::transaction(function () use ($pId, $sesi, $nomorKursi, $request) {
                $r = $this->tetapkanPeserta($pId, $sesi, $nomorKursi);
                SiwisHelper::logActivity($request->user()->id, "Admin menetapkan {$r['nama']} sebagai peserta ({$r['nomor']})");
                return $r;
            });

            return response()->json(['message' => "Berhasil ditetapkan sebagai peserta wisuda ({$result['nomor']})."], 201);
        } catch (\Exception $e) {
            $code = $e->getCode();
            if ($code >= 400 && $code < 500) {
                return response()->json(['message' => $e->getMessage()], $code);
            }
            throw $e;
        }
    }

    public function storePesertaMassal(Request $request): JsonResponse
    {
        $periodeId = (int) $request->input('periode_id');
        if (!$periodeId) {
            return response()->json(['message' => 'Pilih periode terlebih dahulu.'], 400);
        }

        $list = DB::table('pendaftaran_wisuda as p')
            ->join('mahasiswa as m', 'm.id', '=', 'p.mahasiswa_id')
            ->where('p.periode_id', $periodeId)
            ->where('p.status', 'Disetujui')
            ->orderBy('m.fakultas')
            ->orderBy('m.program_studi')
            ->orderBy('m.nim')
            ->pluck('p.id');

        if ($list->isEmpty()) {
            return response()->json(['message' => 'Tidak ada mahasiswa berstatus Disetujui pada periode ini.'], 400);
        }

        $sesi = $request->input('sesi');

        DB::transaction(function () use ($list, $sesi, $request) {
            foreach ($list as $pId) {
                $this->tetapkanPeserta($pId, $sesi, null);
            }
            SiwisHelper::logActivity($request->user()->id, "Admin menetapkan {$list->count()} peserta wisuda secara massal");
        });

        return response()->json(['message' => "{$list->count()} mahasiswa berhasil ditetapkan sebagai peserta wisuda."], 201);
    }

    public function updatePeserta(Request $request, int $id): JsonResponse
    {
        $peserta = PesertaWisuda::find($id);
        if (!$peserta) {
            return response()->json(['message' => 'Peserta tidak ditemukan.'], 404);
        }

        $peserta->sesi = $request->input('sesi') ?: null;
        $peserta->nomor_kursi = $request->input('nomor_kursi') ?: null;
        $peserta->save();

        SiwisHelper::logActivity($request->user()->id, 'Admin memperbarui sesi/nomor kursi peserta');

        return response()->json(['message' => 'Data peserta diperbarui.']);
    }

    public function destroyPeserta(Request $request, int $id): JsonResponse
    {
        $peserta = PesertaWisuda::find($id);
        if (!$peserta) {
            return response()->json(['message' => 'Peserta tidak ditemukan.'], 404);
        }

        $pendaftaranId = $peserta->pendaftaran_id;
        $peserta->delete();

        DB::table('pendaftaran_wisuda')->where('id', $pendaftaranId)->update([
            'status' => 'Disetujui',
            'updated_at' => now(),
        ]);

        SiwisHelper::logActivity($request->user()->id, 'Admin membatalkan penetapan peserta wisuda');

        return response()->json(['message' => 'Penetapan peserta dibatalkan. Status kembali Disetujui.']);
    }

    // ---------- Jadwal ----------
    public function indexJadwal(): JsonResponse
    {
        $rows = DB::select("
            SELECT j.*, pr.nama_periode,
                   (SELECT COUNT(*) FROM peserta_wisuda ps
                    JOIN pendaftaran_wisuda p ON p.id = ps.pendaftaran_id
                    WHERE p.periode_id = j.periode_id AND ps.sesi = j.sesi) AS jumlah_peserta
            FROM jadwal_wisuda j
            JOIN periode_wisuda pr ON pr.id = j.periode_id
            ORDER BY j.tanggal DESC, j.sesi
        ");

        return response()->json($rows);
    }

    private function validateJadwal(Request $request): ?string
    {
        if (!(int) $request->input('periode_id')) return 'Periode wajib dipilih.';
        if (!trim((string) $request->input('sesi', ''))) return 'Sesi wajib diisi (contoh: Sesi 1).';
        if (!$request->input('tanggal')) return 'Tanggal wajib diisi.';
        if (!trim((string) $request->input('waktu', ''))) return 'Waktu wajib diisi (contoh: 08.00 - 11.00 WIB).';
        if (!trim((string) $request->input('lokasi', ''))) return 'Lokasi wajib diisi.';
        return null;
    }

    public function storeJadwal(Request $request): JsonResponse
    {
        $err = $this->validateJadwal($request);
        if ($err) return response()->json(['message' => $err], 400);

        $sesi = trim((string) $request->input('sesi'));
        JadwalWisuda::create([
            'periode_id' => (int) $request->input('periode_id'),
            'sesi' => $sesi,
            'tanggal' => $request->input('tanggal'),
            'waktu' => trim((string) $request->input('waktu')),
            'lokasi' => trim((string) $request->input('lokasi')),
            'keterangan' => trim((string) $request->input('keterangan', '')) ?: null,
        ]);

        SiwisHelper::logActivity($request->user()->id, "Admin membuat jadwal {$sesi}");

        return response()->json(['message' => 'Jadwal wisuda ditambahkan.'], 201);
    }

    public function updateJadwal(Request $request, int $id): JsonResponse
    {
        $err = $this->validateJadwal($request);
        if ($err) return response()->json(['message' => $err], 400);

        $jadwal = JadwalWisuda::find($id);
        if (!$jadwal) {
            return response()->json(['message' => 'Jadwal tidak ditemukan.'], 404);
        }

        $sesi = trim((string) $request->input('sesi'));
        $jadwal->update([
            'periode_id' => (int) $request->input('periode_id'),
            'sesi' => $sesi,
            'tanggal' => $request->input('tanggal'),
            'waktu' => trim((string) $request->input('waktu')),
            'lokasi' => trim((string) $request->input('lokasi')),
            'keterangan' => trim((string) $request->input('keterangan', '')) ?: null,
        ]);

        SiwisHelper::logActivity($request->user()->id, "Admin memperbarui jadwal {$sesi}");

        return response()->json(['message' => 'Jadwal wisuda diperbarui.']);
    }

    public function destroyJadwal(Request $request, int $id): JsonResponse
    {
        JadwalWisuda::where('id', $id)->delete();
        SiwisHelper::logActivity($request->user()->id, 'Admin menghapus jadwal wisuda');

        return response()->json(['message' => 'Jadwal wisuda dihapus.']);
    }

    // ---------- Kehadiran ----------
    public function indexKehadiran(Request $request): JsonResponse
    {
        $rows = SiwisHelper::queryPendaftar(array_merge($request->query(), ['status_peserta' => 'Peserta']));
        return response()->json($rows);
    }

    public function scanKehadiran(Request $request): JsonResponse
    {
        $nomor = SiwisHelper::parseKode($request->input('kode'));
        if (!$nomor) {
            return response()->json(['message' => 'Kode QR / nomor peserta kosong.'], 400);
        }

        $row = DB::selectOne("
            SELECT ps.*, p.periode_id, p.id AS pendaftaran_id, m.nim, m.nama, m.fakultas, m.program_studi, pr.nama_periode, pr.kode AS kode_periode
            FROM peserta_wisuda ps
            JOIN pendaftaran_wisuda p ON p.id = ps.pendaftaran_id
            JOIN mahasiswa m ON m.id = p.mahasiswa_id
            JOIN periode_wisuda pr ON pr.id = p.periode_id
            WHERE ps.nomor_peserta = ?
        ", [$nomor]);

        if (!$row) {
            return response()->json(['message' => "Peserta dengan nomor {$nomor} tidak ditemukan."], 404);
        }

        return response()->json($row);
    }

    public function updateKehadiran(Request $request, int $id): JsonResponse
    {
        $status = (string) $request->input('status');
        if (!in_array($status, ['Hadir', 'Belum Hadir', 'Tidak Hadir'])) {
            return response()->json(['message' => 'Status kehadiran tidak valid.'], 400);
        }

        $peserta = PesertaWisuda::find($id);
        if (!$peserta) {
            return response()->json(['message' => 'Peserta tidak ditemukan.'], 404);
        }

        $peserta->status_kehadiran = $status;
        $peserta->waktu_hadir = $status === 'Hadir' ? now() : null;
        $peserta->save();

        $info = DB::selectOne("
            SELECT ps.*, p.periode_id, p.id AS pendaftaran_id, m.nim, m.nama, m.fakultas, m.program_studi, pr.nama_periode, pr.kode AS kode_periode
            FROM peserta_wisuda ps
            JOIN pendaftaran_wisuda p ON p.id = ps.pendaftaran_id
            JOIN mahasiswa m ON m.id = p.mahasiswa_id
            JOIN periode_wisuda pr ON pr.id = p.periode_id
            WHERE ps.id = ?
        ", [$id]);

        SiwisHelper::logActivity($request->user()->id, "Kehadiran {$info->nama} ({$info->nomor_peserta}) dicatat: {$status}");

        return response()->json([
            'message' => "Kehadiran {$info->nama} dicatat: {$status}.",
            'peserta' => $info,
        ]);
    }

    // ---------- Laporan ----------
    public function laporan(Request $request): JsonResponse
    {
        $rows = SiwisHelper::queryPendaftar($request->query(), ['exclude_draft' => true]);
        return response()->json($rows);
    }

    // ---------- Jenis Berkas ----------
    public function indexJenisBerkas(): JsonResponse
    {
        $rows = JenisBerkas::orderBy('urutan')->orderBy('id')->get();
        return response()->json($rows);
    }

    public function storeJenisBerkas(Request $request): JsonResponse
    {
        $nama = trim((string) $request->input('nama', ''));
        if (!$nama) {
            return response()->json(['message' => 'Nama berkas wajib diisi.'], 400);
        }

        JenisBerkas::create([
            'nama' => $nama,
            'keterangan' => trim((string) $request->input('keterangan', '')) ?: null,
            'wajib' => (bool) $request->input('wajib', true),
            'is_pembayaran' => false,
            'aktif' => $request->input('aktif') !== false,
            'urutan' => (int) $request->input('urutan', 99),
        ]);

        return response()->json(['message' => 'Jenis berkas ditambahkan.'], 201);
    }

    public function updateJenisBerkas(Request $request, int $id): JsonResponse
    {
        $nama = trim((string) $request->input('nama', ''));
        if (!$nama) {
            return response()->json(['message' => 'Nama berkas wajib diisi.'], 400);
        }

        $jb = JenisBerkas::find($id);
        if (!$jb) {
            return response()->json(['message' => 'Jenis berkas tidak ditemukan.'], 404);
        }

        $jb->update([
            'nama' => $nama,
            'keterangan' => trim((string) $request->input('keterangan', '')) ?: null,
            'wajib' => (bool) $request->input('wajib', true),
            'aktif' => (bool) $request->input('aktif', true),
            'urutan' => (int) $request->input('urutan', 99),
        ]);

        return response()->json(['message' => 'Jenis berkas diperbarui.']);
    }

    public function destroyJenisBerkas(int $id): JsonResponse
    {
        $count = BerkasWisuda::where('jenis_berkas_id', $id)->count();
        if ($count > 0) {
            return response()->json(['message' => 'Jenis berkas sudah dipakai mahasiswa. Nonaktifkan saja, jangan dihapus.'], 400);
        }

        JenisBerkas::where('id', $id)->delete();
        return response()->json(['message' => 'Jenis berkas dihapus.']);
    }

    // ---------- Mahasiswa ----------
    public function indexMahasiswa(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $query = Mahasiswa::query();

        if ($q !== '') {
            $query->where(function ($b) use ($q) {
                $b->where('nim', 'like', "%{$q}%")
                  ->orWhere('nama', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderBy('nim')->limit(500)->get();
        return response()->json($rows);
    }

    private function validateMhs(Request $request, bool $baru): ?string
    {
        $nim = trim((string) $request->input('nim', ''));
        if (!preg_match('/^[0-9]{6,20}$/', $nim)) {
            return 'NIM harus berupa angka (6-20 digit).';
        }
        if (!trim((string) $request->input('nama', ''))) {
            return 'Nama wajib diisi.';
        }
        if (!filter_var($request->input('email'), FILTER_VALIDATE_EMAIL)) {
            return 'Email tidak valid.';
        }
        if (!trim((string) $request->input('fakultas', '')) || !trim((string) $request->input('program_studi', ''))) {
            return 'Fakultas dan program studi wajib diisi.';
        }
        $ipk = $request->input('ipk');
        if (!is_numeric($ipk) || $ipk < 0 || $ipk > 4) {
            return 'IPK harus antara 0.00 - 4.00.';
        }
        if (!in_array($request->input('status_kelulusan'), ['Lulus', 'Belum Lulus'])) {
            return 'Status kelulusan tidak valid.';
        }
        if ($baru && strlen((string) $request->input('password', '')) < 6) {
            return 'Password awal minimal 6 karakter.';
        }
        return null;
    }

    public function storeMahasiswa(Request $request): JsonResponse
    {
        $err = $this->validateMhs($request, true);
        if ($err) return response()->json(['message' => $err], 400);

        $nim = trim((string) $request->input('nim'));
        $email = trim((string) $request->input('email'));

        if (User::where('email', $email)->exists() || Mahasiswa::where('nim', $nim)->exists()) {
            return response()->json(['message' => 'NIM atau email sudah terdaftar.'], 400);
        }

        DB::transaction(function () use ($request, $nim, $email) {
            $user = User::create([
                'email' => $email,
                'password' => Hash::make((string) $request->input('password')),
                'role' => 'mahasiswa',
                'nama' => trim((string) $request->input('nama')),
            ]);

            Mahasiswa::create([
                'user_id' => $user->id,
                'nim' => $nim,
                'nama' => trim((string) $request->input('nama')),
                'email' => $email,
                'no_hp' => trim((string) $request->input('no_hp', '')) ?: null,
                'fakultas' => trim((string) $request->input('fakultas')),
                'program_studi' => trim((string) $request->input('program_studi')),
                'ipk' => (float) $request->input('ipk'),
                'judul_skripsi' => trim((string) $request->input('judul_skripsi', '')) ?: null,
                'status_kelulusan' => $request->input('status_kelulusan'),
                'tahun_masuk' => $request->input('tahun_masuk') ?: null,
                'tahun_lulus' => $request->input('tahun_lulus') ?: null,
            ]);

            SiwisHelper::logActivity($request->user()->id, "Admin menambah data mahasiswa {$nim}");
        });

        return response()->json(['message' => 'Data mahasiswa ditambahkan.'], 201);
    }

    public function updateMahasiswa(Request $request, int $id): JsonResponse
    {
        $err = $this->validateMhs($request, false);
        if ($err) return response()->json(['message' => $err], 400);

        $mhs = Mahasiswa::find($id);
        if (!$mhs) {
            return response()->json(['message' => 'Mahasiswa tidak ditemukan.'], 404);
        }

        $nim = trim((string) $request->input('nim'));
        $email = trim((string) $request->input('email'));

        $dupMhs = Mahasiswa::where('nim', $nim)->where('id', '<>', $id)->exists();
        $dupUser = User::where('email', $email)->where('id', '<>', $mhs->user_id)->exists();
        if ($dupMhs || $dupUser) {
            return response()->json(['message' => 'NIM atau email sudah dipakai mahasiswa lain.'], 400);
        }

        $password = (string) $request->input('password', '');
        if ($password !== '' && strlen($password) < 6) {
            return response()->json(['message' => 'Password minimal 6 karakter.'], 400);
        }

        DB::transaction(function () use ($request, $mhs, $nim, $email, $password) {
            $nama = trim((string) $request->input('nama'));
            $mhs->update([
                'nim' => $nim,
                'nama' => $nama,
                'email' => $email,
                'no_hp' => trim((string) $request->input('no_hp', '')) ?: null,
                'fakultas' => trim((string) $request->input('fakultas')),
                'program_studi' => trim((string) $request->input('program_studi')),
                'ipk' => (float) $request->input('ipk'),
                'judul_skripsi' => trim((string) $request->input('judul_skripsi', '')) ?: null,
                'status_kelulusan' => $request->input('status_kelulusan'),
                'tahun_masuk' => $request->input('tahun_masuk') ?: null,
                'tahun_lulus' => $request->input('tahun_lulus') ?: null,
            ]);

            $userData = ['nama' => $nama, 'email' => $email];
            if ($password !== '') {
                $userData['password'] = Hash::make($password);
            }
            User::where('id', $mhs->user_id)->update($userData);

            SiwisHelper::logActivity($request->user()->id, "Admin memperbarui data mahasiswa {$nim}");
        });

        return response()->json(['message' => 'Data mahasiswa diperbarui.']);
    }

    public function destroyMahasiswa(Request $request, int $id): JsonResponse
    {
        $mhs = Mahasiswa::find($id);
        if (!$mhs) {
            return response()->json(['message' => 'Mahasiswa tidak ditemukan.'], 404);
        }

        $daftarCount = PendaftaranWisuda::where('mahasiswa_id', $id)->count();
        if ($daftarCount > 0) {
            return response()->json(['message' => 'Mahasiswa sudah memiliki pendaftaran wisuda dan tidak dapat dihapus.'], 400);
        }

        $nim = $mhs->nim;
        $userId = $mhs->user_id;

        // Delete user cascades to mahasiswa
        User::where('id', $userId)->delete();
        SiwisHelper::logActivity($request->user()->id, "Admin menghapus data mahasiswa {$nim}");

        return response()->json(['message' => 'Data mahasiswa dihapus.']);
    }

    public function getNotifikasi(Request $request): JsonResponse
    {
        $rows = Notifikasi::where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get();

        return response()->json($rows);
    }

    public function bacaNotifikasi(Request $request): JsonResponse
    {
        Notifikasi::where('user_id', $request->user()->id)->update(['dibaca' => true]);
        return response()->json(['message' => 'ok']);
    }

    public function getQr(string $nomor): JsonResponse
    {
        return response()->json(['qr' => SiwisHelper::qrDataUrl($nomor)]);
    }
}
