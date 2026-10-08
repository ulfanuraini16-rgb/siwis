<?php

namespace App\Http\Controllers;

use App\Models\PeriodeWisuda;
use App\Services\SiwisHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    private function deskripsiFilter(array $q): string
    {
        $parts = [];
        if (!empty($q['periode_id'])) {
            $p = PeriodeWisuda::find($q['periode_id']);
            if ($p) $parts[] = 'Periode: ' . $p->nama_periode;
        }
        if (!empty($q['fakultas'])) $parts[] = 'Fakultas: ' . $q['fakultas'];
        if (!empty($q['program_studi'])) $parts[] = 'Prodi: ' . $q['program_studi'];
        if (!empty($q['status'])) $parts[] = 'Status: ' . $q['status'];
        if (!empty($q['sesi'])) $parts[] = 'Sesi: ' . $q['sesi'];
        if (!empty($q['status_kehadiran'])) $parts[] = 'Kehadiran: ' . $q['status_kehadiran'];

        $tgl = now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i');
        return (count($parts) ? implode(' | ', $parts) : 'Semua data') . '  —  Dicetak: ' . $tgl;
    }

    private function formatSheet(Worksheet $sheet, string $judul, string $subjudul, array $kolom, array $baris): void
    {
        $n = count($kolom);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);

        // Judul & Subjudul
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->setCellValue('A1', $judul);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF14304A');

        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->setCellValue('A2', $subjudul);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FF6B7A89');

        // Header Row (Row 4)
        $sheet->getRowDimension(4)->setRowHeight(24);
        foreach ($kolom as $i => $k) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $cell = "{$colLetter}4";

            $sheet->setCellValue($cell, $k['header']);
            $sheet->getColumnDimension($colLetter)->setWidth($k['width'] ?? 16);

            $sheet->getStyle($cell)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF14304A'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ]);
        }

        // Data Rows
        $rowIndex = 5;
        foreach ($baris as $idx => $r) {
            $rArray = (array) $r;
            foreach ($kolom as $i => $k) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $cell = "{$colLetter}{$rowIndex}";

                $val = ($k['key'] === 'no') ? ($idx + 1) : ($rArray[$k['key']] ?? '');
                $sheet->setCellValue($cell, $val);

                $sheet->getStyle($cell)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_HAIR],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
            }
            $rowIndex++;
        }

        if (empty($baris)) {
            $sheet->setCellValue('A5', 'Tidak ada data untuk filter yang dipilih.');
        }

        // Freeze pane
        $sheet->freezePane('A5');
    }

    private function responseExcel(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $dateStr = now()->format('Y-m-d');
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}-{$dateStr}.xlsx\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    public function rekap(Request $request): StreamedResponse
    {
        $q = $request->query();
        $filter = SiwisHelper::buildWhere($q, ['exclude_draft' => true]);

        $agg = "
            COUNT(*) AS pendaftar,
            SUM(p.status = 'Menunggu Verifikasi') AS menunggu,
            SUM(p.status = 'Revisi') AS revisi,
            SUM(p.status IN ('Disetujui','Peserta Wisuda')) AS disetujui,
            SUM(ps.id IS NOT NULL) AS peserta,
            SUM(ps.status_kehadiran = 'Hadir') AS hadir,
            SUM(ps.status_kehadiran = 'Tidak Hadir') AS tidak_hadir
        ";

        $from = "
            FROM pendaftaran_wisuda p
            JOIN mahasiswa m ON m.id = p.mahasiswa_id
            JOIN periode_wisuda pr ON pr.id = p.periode_id
            LEFT JOIN peserta_wisuda ps ON ps.pendaftaran_id = p.id
            {$filter['sql']}
        ";

        $fak = DB::select("SELECT m.fakultas, {$agg} {$from} GROUP BY m.fakultas ORDER BY m.fakultas", $filter['params']);
        $prodi = DB::select("SELECT m.fakultas, m.program_studi, {$agg} {$from} GROUP BY m.fakultas, m.program_studi ORDER BY m.fakultas, m.program_studi", $filter['params']);

        $angkaKolom = [
            ['header' => 'Pendaftar', 'key' => 'pendaftar', 'width' => 12],
            ['header' => 'Menunggu Verifikasi', 'key' => 'menunggu', 'width' => 14],
            ['header' => 'Revisi', 'key' => 'revisi', 'width' => 10],
            ['header' => 'Disetujui', 'key' => 'disetujui', 'width' => 12],
            ['header' => 'Peserta Wisuda', 'key' => 'peserta', 'width' => 14],
            ['header' => 'Hadir', 'key' => 'hadir', 'width' => 10],
            ['header' => 'Tidak Hadir', 'key' => 'tidak_hadir', 'width' => 12],
        ];

        $ket = $this->deskripsiFilter($q);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator('SIWIS');

        // Sheet 1: Rekap Fakultas
        $s1 = $spreadsheet->getActiveSheet();
        $s1->setTitle('Rekap Fakultas');
        $kolomFak = array_merge([
            ['header' => 'No', 'key' => 'no', 'width' => 6],
            ['header' => 'Fakultas', 'key' => 'fakultas', 'width' => 30],
        ], $angkaKolom);
        $this->formatSheet($s1, 'Rekap Wisudawan per Fakultas', $ket, $kolomFak, $fak);

        // Sheet 2: Rekap Prodi
        $s2 = $spreadsheet->createSheet();
        $s2->setTitle('Rekap Prodi');
        $kolomProdi = array_merge([
            ['header' => 'No', 'key' => 'no', 'width' => 6],
            ['header' => 'Fakultas', 'key' => 'fakultas', 'width' => 30],
            ['header' => 'Program Studi', 'key' => 'program_studi', 'width' => 30],
        ], $angkaKolom);
        $this->formatSheet($s2, 'Rekap Wisudawan per Program Studi', $ket, $kolomProdi, $prodi);

        SiwisHelper::logActivity($request->user()->id, 'Admin mengekspor rekap fakultas/prodi ke Excel');

        return $this->responseExcel($spreadsheet, 'rekap-fakultas-prodi');
    }

    public function laporanPdf(Request $request)
    {
        $q = $request->query();
        $rows = SiwisHelper::queryPendaftar($q, ['exclude_draft' => true]);
        $keterangan = $this->deskripsiFilter($q);
        $kampus = env('NAMA_KAMPUS', 'Universitas Contoh Nusantara');

        $html = view('laporan_pdf', [
            'rows' => $rows,
            'keterangan' => $keterangan,
            'kampus' => $kampus,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'landscape');

        return $pdf->download('laporan-wisuda.pdf');
    }

    public function exportTipe(Request $request, string $tipe): StreamedResponse|JsonResponse
    {
        $q = $request->query();

        $kolomDef = [
            'no' => ['header' => 'No', 'key' => 'no', 'width' => 6],
            'nim' => ['header' => 'NIM', 'key' => 'nim', 'width' => 14],
            'nama' => ['header' => 'Nama', 'key' => 'nama', 'width' => 30],
            'fakultas' => ['header' => 'Fakultas', 'key' => 'fakultas', 'width' => 26],
            'program_studi' => ['header' => 'Program Studi', 'key' => 'program_studi', 'width' => 26],
            'ipk' => ['header' => 'IPK', 'key' => 'ipk', 'width' => 8],
            'status' => ['header' => 'Status', 'key' => 'status', 'width' => 20],
            'nomor_peserta' => ['header' => 'No Peserta', 'key' => 'nomor_peserta', 'width' => 18],
            'sesi' => ['header' => 'Sesi', 'key' => 'sesi', 'width' => 12],
            'kehadiran' => ['header' => 'Kehadiran', 'key' => 'status_kehadiran', 'width' => 14],
            'periode' => ['header' => 'Periode', 'key' => 'nama_periode', 'width' => 28],
            'tgl_daftar' => ['header' => 'Tanggal Daftar', 'key' => 'tanggal_daftar', 'width' => 20],
            'email' => ['header' => 'Email', 'key' => 'email', 'width' => 28],
            'hp' => ['header' => 'No HP', 'key' => 'no_hp', 'width' => 16],
            'judul' => ['header' => 'Judul Skripsi/TA', 'key' => 'judul_skripsi', 'width' => 50],
            'kursi' => ['header' => 'No Kursi', 'key' => 'nomor_kursi', 'width' => 10],
            'waktu_hadir' => ['header' => 'Waktu Hadir', 'key' => 'waktu_hadir', 'width' => 20],
            'berkas' => ['header' => 'Berkas Valid', 'key' => 'berkas_ket', 'width' => 14],
        ];

        $definisi = [
            'peserta' => [
                'judul' => 'Daftar Peserta Wisuda',
                'file' => 'peserta-wisuda',
                'ambil' => fn ($query) => SiwisHelper::queryPendaftar(array_merge($query, ['status_peserta' => 'Peserta'])),
                'keys' => ['no', 'nomor_peserta', 'nim', 'nama', 'fakultas', 'program_studi', 'ipk', 'periode', 'sesi', 'kursi', 'kehadiran'],
            ],
            'pendaftar' => [
                'judul' => 'Data Pendaftar Wisuda',
                'file' => 'data-pendaftar',
                'ambil' => fn ($query) => SiwisHelper::queryPendaftar($query, ['exclude_draft' => true]),
                'keys' => ['no', 'nim', 'nama', 'email', 'hp', 'fakultas', 'program_studi', 'ipk', 'judul', 'periode', 'tgl_daftar', 'berkas', 'status'],
            ],
            'disetujui' => [
                'judul' => 'Data Mahasiswa Disetujui',
                'file' => 'peserta-disetujui',
                'ambil' => function ($query) {
                    $res = SiwisHelper::queryPendaftar(array_merge($query, ['status' => null]), ['exclude_draft' => true]);
                    return array_values(array_filter($res, fn ($r) => in_array($r->status, ['Disetujui', 'Peserta Wisuda'])));
                },
                'keys' => ['no', 'nim', 'nama', 'fakultas', 'program_studi', 'ipk', 'periode', 'status', 'nomor_peserta', 'sesi'],
            ],
            'kehadiran' => [
                'judul' => 'Daftar Kehadiran Wisuda',
                'file' => 'daftar-kehadiran',
                'ambil' => fn ($query) => SiwisHelper::queryPendaftar(array_merge($query, ['status_peserta' => 'Peserta'])),
                'keys' => ['no', 'nomor_peserta', 'nim', 'nama', 'fakultas', 'program_studi', 'sesi', 'kehadiran', 'waktu_hadir'],
            ],
            'laporan' => [
                'judul' => 'Laporan Wisudawan',
                'file' => 'laporan-wisuda',
                'ambil' => fn ($query) => SiwisHelper::queryPendaftar($query, ['exclude_draft' => true]),
                'keys' => ['no', 'nim', 'nama', 'fakultas', 'program_studi', 'ipk', 'status', 'nomor_peserta', 'sesi', 'kehadiran'],
            ],
        ];

        if (!isset($definisi[$tipe])) {
            return response()->json(['message' => 'Jenis export tidak dikenal.'], 404);
        }

        $def = $definisi[$tipe];
        $rows = ($def['ambil'])($q);

        // Add berkas_ket
        foreach ($rows as $r) {
            $r->berkas_ket = "{$r->berkas_valid}/{$r->berkas_wajib}";
        }

        $kolom = [];
        foreach ($def['keys'] as $k) {
            if (isset($kolomDef[$k])) {
                $kolom[] = $kolomDef[$k];
            }
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator('SIWIS');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($def['judul'], 0, 30));

        $ket = $this->deskripsiFilter($q);
        $this->formatSheet($sheet, $def['judul'], $ket, $kolom, $rows);

        SiwisHelper::logActivity($request->user()->id, "Admin mengekspor {$def['judul']} ke Excel (" . count($rows) . " baris)");

        return $this->responseExcel($spreadsheet, $def['file']);
    }
}
