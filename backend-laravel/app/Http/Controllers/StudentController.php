<?php

namespace App\Http\Controllers;

use App\Models\BerkasWisuda;
use App\Models\JadwalWisuda;
use App\Models\JenisBerkas;
use App\Models\Mahasiswa;
use App\Models\Notifikasi;
use App\Models\Pembayaran;
use App\Models\PendaftaranWisuda;
use App\Models\PeriodeWisuda;
use App\Services\SiwisHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentController extends Controller
{
    private function getMahasiswa(Request $request): ?Mahasiswa
    {
        return Mahasiswa::where('user_id', $request->user()->id)->first();
    }

    private function getPendaftaranTerbaru(int $mhsId): ?object
    {
        $latest = PendaftaranWisuda::where('mahasiswa_id', $mhsId)->orderBy('id', 'desc')->first();
        return $latest ? SiwisHelper::getPendaftaranDetail($latest->id) : null;
    }

    public function dashboard(Request $request): JsonResponse
    {
        $mhs = $this->getMahasiswa($request);
        if (!$mhs) {
            return response()->json(['message' => 'Data mahasiswa untuk akun ini belum dibuat. Hubungi Admin.'], 404);
        }

        $pendaftaran = $this->getPendaftaranTerbaru($mhs->id);
        $jadwal = [];

        if ($pendaftaran) {
            $query = JadwalWisuda::where('periode_id', $pendaftaran->periode_id)
                ->orderBy('tanggal')
                ->orderBy('sesi');

            if ($pendaftaran->sesi) {
                $query->where('sesi', $pendaftaran->sesi);
            }
            $jadwal = $query->get();
        }

        return response()->json([
            'mahasiswa' => $mhs,
            'pendaftaran' => $pendaftaran,
            'jadwal' => $jadwal,
        ]);
    }

    public function getProfile(Request $request): JsonResponse
    {
        $mhs = $this->getMahasiswa($request);
        if (!$mhs) {
            return response()->json(['message' => 'Data mahasiswa tidak ditemukan.'], 404);
        }

        $p = $this->getPendaftaranTerbaru($mhs->id);
        $terkunci = $p && !in_array($p->status, ['Draft', 'Revisi']);

        return response()->json([
            'mahasiswa' => $mhs,
            'terkunci' => $terkunci,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $mhs = $this->getMahasiswa($request);
        if (!$mhs) {
            return response()->json(['message' => 'Data mahasiswa tidak ditemukan.'], 404);
        }

        $p = $this->getPendaftaranTerbaru($mhs->id);
        if ($p && !in_array($p->status, ['Draft', 'Revisi'])) {
            return response()->json(['message' => 'Data diri terkunci karena pendaftaran sedang diverifikasi atau sudah diproses.'], 403);
        }

        $email = trim((string) $request->input('email', ''));
        $noHp = trim((string) $request->input('no_hp', ''));
        $judulSkripsi = trim((string) $request->input('judul_skripsi', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Format email tidak valid.'], 400);
        }

        if (!preg_match('/^[0-9+\-\s]{8,20}$/', $noHp)) {
            return response()->json(['message' => 'Nomor HP tidak valid (8-20 digit).'], 400);
        }

        if ($judulSkripsi === '') {
            return response()->json(['message' => 'Judul skripsi/tugas akhir wajib diisi.'], 400);
        }

        $mhs->email = $email;
        $mhs->no_hp = $noHp;
        $mhs->judul_skripsi = $judulSkripsi;
        $mhs->save();

        SiwisHelper::logActivity($request->user()->id, "{$mhs->nama} memperbarui data diri");

        return response()->json(['message' => 'Data diri berhasil disimpan.']);
    }

    public function getPeriode(): JsonResponse
    {
        $rows = PeriodeWisuda::where('status', 'Dibuka')->orderBy('tanggal_wisuda')->get();
        return response()->json($rows);
    }

    public function getPendaftaran(Request $request): JsonResponse
    {
        $mhs = $this->getMahasiswa($request);
        return response()->json([
            'pendaftaran' => $this->getPendaftaranTerbaru($mhs->id),
            'mahasiswa' => $mhs,
            'maks_ukuran_mb' => 2,
        ]);
    }

    public function storePendaftaran(Request $request): JsonResponse
    {
        $mhs = $this->getMahasiswa($request);
        $periodeId = (int) $request->input('periode_id');

        $periode = PeriodeWisuda::where('id', $periodeId)->where('status', 'Dibuka')->first();
        if (!$periode) {
            return response()->json(['message' => 'Periode wisuda tidak ditemukan atau sudah ditutup.'], 400);
        }

        $alasan = [];
        if ($mhs->status_kelulusan !== 'Lulus') {
            $alasan[] = 'Status kelulusan Anda belum "Lulus".';
        }
        if ((float) $mhs->ipk < (float) $periode->min_ipk) {
            $alasan[] = "IPK Anda ({$mhs->ipk}) di bawah syarat minimal {$periode->min_ipk}.";
        }
        if (!$mhs->judul_skripsi) {
            $alasan[] = 'Judul skripsi/tugas akhir belum diisi (lengkapi di menu Data Diri).';
        }

        if (count($alasan) > 0) {
            return response()->json([
                'message' => 'Anda belum memenuhi syarat kelayakan.',
                'alasan' => $alasan,
            ], 400);
        }

        $aktif = PendaftaranWisuda::where('mahasiswa_id', $mhs->id)->where('status', '<>', 'Ditolak')->exists();
        if ($aktif) {
            return response()->json(['message' => 'Anda sudah memiliki pendaftaran wisuda yang masih aktif.'], 400);
        }

        $duplicate = PendaftaranWisuda::where('mahasiswa_id', $mhs->id)->where('periode_id', $periodeId)->exists();
        if ($duplicate) {
            return response()->json(['message' => 'Anda pernah mendaftar pada periode ini.'], 400);
        }

        $pendaftaran = PendaftaranWisuda::create([
            'mahasiswa_id' => $mhs->id,
            'periode_id' => $periodeId,
            'status' => 'Draft',
        ]);

        Pembayaran::create([
            'pendaftaran_id' => $pendaftaran->id,
            'nominal' => $periode->biaya,
            'status' => 'Belum Bayar',
        ]);

        SiwisHelper::logActivity($request->user()->id, "{$mhs->nama} membuat draft pendaftaran wisuda {$periode->nama_periode}");

        return response()->json([
            'message' => 'Draft pendaftaran dibuat. Lanjutkan dengan mengupload berkas.',
            'id' => $pendaftaran->id,
        ], 201);
    }

    public function submitPendaftaran(Request $request): JsonResponse
    {
        $mhs = $this->getMahasiswa($request);
        $p = $this->getPendaftaranTerbaru($mhs->id);

        if (!$p) {
            return response()->json(['message' => 'Belum ada pendaftaran.'], 404);
        }

        if (!in_array($p->status, ['Draft', 'Revisi'])) {
            return response()->json(['message' => 'Pendaftaran sudah dikirim atau sudah diproses.'], 400);
        }

        $kurang = [];
        $perluRevisi = [];

        foreach ($p->berkas as $b) {
            if ($b->wajib && $b->status === 'Belum Upload') {
                $kurang[] = $b->jenis_nama;
            }
            if (in_array($b->status, ['Revisi', 'Ditolak'])) {
                $perluRevisi[] = $b->jenis_nama;
            }
        }

        if (count($kurang) > 0) {
            return response()->json(['message' => 'Berkas wajib belum lengkap: ' . implode(', ', $kurang) . '.'], 400);
        }

        if (count($perluRevisi) > 0) {
            return response()->json(['message' => 'Perbaiki dan upload ulang berkas berikut: ' . implode(', ', $perluRevisi) . '.'], 400);
        }

        DB::table('pendaftaran_wisuda')->where('id', $p->id)->update([
            'status' => 'Menunggu Verifikasi',
            'tanggal_daftar' => $p->tanggal_daftar ? $p->tanggal_daftar : now(),
            'catatan' => null,
            'updated_at' => now(),
        ]);

        SiwisHelper::logActivity($request->user()->id, "{$mhs->nama} mengirim pendaftaran wisuda");
        SiwisHelper::notify($request->user()->id, 'Pendaftaran wisuda Anda berhasil dikirim. Berkas Anda sedang diverifikasi.');
        SiwisHelper::notifAdmins("{$mhs->nama} ({$mhs->nim}) mengirim pendaftaran wisuda.");

        return response()->json(['message' => 'Pendaftaran berhasil dikirim dan menunggu verifikasi.']);
    }

    public function uploadBerkas(Request $request): JsonResponse
    {
        $mhs = $this->getMahasiswa($request);
        $p = $this->getPendaftaranTerbaru($mhs->id);

        if (!$p) {
            return response()->json(['message' => 'Buat pendaftaran wisuda terlebih dahulu.'], 400);
        }

        if (!$request->hasFile('file')) {
            return response()->json(['message' => 'Pilih file yang akan diupload.'], 400);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowed)) {
            return response()->json(['message' => 'Format file tidak didukung. Gunakan PDF, JPG, atau PNG.'], 400);
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            return response()->json(['message' => 'Ukuran file terlalu besar. Maksimal 2 MB.'], 400);
        }

        $jenisId = (int) $request->input('jenis_berkas_id');
        $jenisItem = null;
        foreach ($p->berkas as $b) {
            if ($b->jenis_id === $jenisId) {
                $jenisItem = $b;
                break;
            }
        }

        if (!$jenisItem) {
            return response()->json(['message' => 'Jenis berkas tidak valid.'], 400);
        }

        $bolehDraft = $p->status === 'Draft';
        $bolehRevisi = $p->status === 'Revisi' && in_array($jenisItem->status, ['Revisi', 'Ditolak', 'Belum Upload']);

        if (!$bolehDraft && !$bolehRevisi) {
            return response()->json(['message' => 'Berkas tidak dapat diubah pada tahap ini. Upload ulang hanya untuk berkas yang diminta revisi.'], 403);
        }

        if ($jenisItem->status === 'Valid') {
            return response()->json(['message' => 'Berkas ini sudah valid dan tidak dapat diganti.'], 403);
        }

        $dir = SiwisHelper::getUploadDirectory();
        $randomName = time() . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        $file->move($dir, $randomName);

        // Cari berkas lama jika sudah pernah upload
        $lama = BerkasWisuda::where('pendaftaran_id', $p->id)
            ->where('jenis_berkas_id', $jenisItem->jenis_id)
            ->first();

        if ($lama) {
            $oldPath = SiwisHelper::findFilePath($lama->file_path);
            if ($oldPath && file_exists($oldPath)) {
                @unlink($oldPath);
            }

            $lama->nama_file = $file->getClientOriginalName();
            $lama->file_path = $randomName;
            $lama->status = 'Menunggu Verifikasi';
            $lama->catatan = null;
            $lama->tanggal_upload = now();
            $lama->tanggal_verifikasi = null;
            $lama->save();
        } else {
            BerkasWisuda::create([
                'pendaftaran_id' => $p->id,
                'jenis_berkas_id' => $jenisItem->jenis_id,
                'nama_file' => $file->getClientOriginalName(),
                'file_path' => $randomName,
                'status' => 'Menunggu Verifikasi',
                'tanggal_upload' => now(),
            ]);
        }

        if ($jenisItem->is_pembayaran) {
            DB::table('pembayaran')->where('pendaftaran_id', $p->id)->update([
                'bukti_pembayaran' => $randomName,
                'status' => 'Menunggu Verifikasi',
                'tanggal_pembayaran' => now(),
            ]);
        }

        $pesan = "Berkas \"{$jenisItem->jenis_nama}\" berhasil diupload.";

        if ($p->status === 'Revisi') {
            $sisa = BerkasWisuda::where('pendaftaran_id', $p->id)
                ->whereIn('status', ['Revisi', 'Ditolak'])
                ->count();

            if ($sisa === 0) {
                DB::table('pendaftaran_wisuda')->where('id', $p->id)->update([
                    'status' => 'Menunggu Verifikasi',
                    'updated_at' => now(),
                ]);

                SiwisHelper::notifAdmins("{$mhs->nama} ({$mhs->nim}) mengirim perbaikan berkas. Menunggu verifikasi ulang.");
                SiwisHelper::notify($request->user()->id, 'Berkas revisi Anda sudah terkirim dan sedang diverifikasi ulang.');
                $pesan .= ' Semua revisi sudah dikirim, pendaftaran kembali menunggu verifikasi.';
            }
        }

        SiwisHelper::logActivity($request->user()->id, "{$mhs->nama} mengupload berkas {$jenisItem->jenis_nama}");

        return response()->json(['message' => $pesan], 201);
    }

    public function downloadBerkas(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $mhs = $this->getMahasiswa($request);
        $berkas = DB::table('berkas_wisuda as b')
            ->join('pendaftaran_wisuda as p', 'p.id', '=', 'b.pendaftaran_id')
            ->where('b.id', $id)
            ->where('p.mahasiswa_id', $mhs->id)
            ->select('b.*')
            ->first();

        if (!$berkas) {
            return response()->json(['message' => 'Berkas tidak ditemukan.'], 404);
        }

        $path = SiwisHelper::findFilePath($berkas->file_path);
        if (!$path) {
            return response()->json(['message' => 'File fisik tidak ditemukan pada server.'], 404);
        }

        return response()->download($path, $berkas->nama_file);
    }

    public function getJadwal(Request $request): JsonResponse
    {
        $mhs = $this->getMahasiswa($request);
        $p = $this->getPendaftaranTerbaru($mhs->id);

        if (!$p) {
            return response()->json(['pendaftaran' => null, 'jadwal' => []]);
        }

        $jadwal = JadwalWisuda::where('periode_id', $p->periode_id)
            ->orderBy('tanggal')
            ->orderBy('sesi')
            ->get();

        return response()->json(['pendaftaran' => $p, 'jadwal' => $jadwal]);
    }

    public function getKartu(Request $request): JsonResponse
    {
        $mhs = $this->getMahasiswa($request);
        $p = $this->getPendaftaranTerbaru($mhs->id);

        if (!$p || empty($p->nomor_peserta)) {
            return response()->json(['message' => 'Kartu peserta tersedia setelah Anda ditetapkan sebagai peserta wisuda.'], 403);
        }

        $jadwal = JadwalWisuda::where('periode_id', $p->periode_id)
            ->where('sesi', $p->sesi ?? '')
            ->first();

        return response()->json([
            'kampus' => env('NAMA_KAMPUS', 'Universitas Islam Negeri Imam Bonjol Padang'),
            'peserta' => $p,
            'jadwal' => $jadwal,
            'qr' => SiwisHelper::qrDataUrl($p->nomor_peserta),
        ]);
    }

    public function downloadKartuPdf(Request $request)
    {
        $mhs = $this->getMahasiswa($request);
        $p = $this->getPendaftaranTerbaru($mhs->id);

        if (!$p || empty($p->nomor_peserta)) {
            return response()->json(['message' => 'Kartu peserta belum tersedia.'], 403);
        }

        $jadwal = JadwalWisuda::where('periode_id', $p->periode_id)
            ->where('sesi', $p->sesi ?? '')
            ->first();

        $qrDataUrl = SiwisHelper::qrDataUrl($p->nomor_peserta);
        $kampus = env('NAMA_KAMPUS', 'Universitas Islam Negeri Imam Bonjol Padang');

        $html = view('kartu_pdf', [
            'p' => $p,
            'jadwal' => $jadwal,
            'qr' => $qrDataUrl,
            'kampus' => $kampus,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper([0, 0, 420, 600]);

        return $pdf->download("kartu-{$p->nomor_peserta}.pdf");
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
}
