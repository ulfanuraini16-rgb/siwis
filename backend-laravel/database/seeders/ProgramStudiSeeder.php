<?php

namespace Database\Seeders;

use App\Models\ProgramStudi;
use Illuminate\Database\Seeder;

class ProgramStudiSeeder extends Seeder
{
    public function run(): void
    {
        $programStudi = [
            'D3' => [
                'Ilmu Perpustakaan',
            ],
            'S1' => [
                'Ilmu Aktuaria',
                'Matematika',
                'Manajemen Pendidikan Islam',
                'Pendidikan Agama Islam',
                'Bimbingan dan Konseling Pendidikan Islam',
                'Tadris Fisika',
                'Hukum Ekonomi Syariah (Muamalah)',
                'Perbandingan Mazhab',
                'Hukum Keluarga (Al-Ahwal asy Syakhshiyah)',
                'Komunikasi dan Penyiaran Islam',
                'Pengembangan Masyarakat Islam',
                'Manajemen Dakwah',
                'Sejarah Peradaban Islam (SPI)',
                'Bahasa dan Sastra Arab',
                'Ilmu Perpustakaan dan Informasi Islam',
                'Tadris Matematika',
                'Ilmu Al-Quran dan Tafsir',
                'Hukum Tatanegara (Siyasah Syar`iyyah)',
                'Tasawuf dan Psikoterapi',
                'Tadris IPS',
                'Tadris Bahasa Inggris',
                'Pendidikan Bahasa Arab',
                'Psikologi Islam',
                'Studi Agama-agama',
                'Ilmu Hadis',
                'Ekonomi Syariah',
                'Aqidah dan Filsafat Islam',
                'Manajemen Bisnis Syariah',
                'Perbankan Syariah',
                'Akuntansi Syariah',
                'Sistem Informasi',
                'Bimbingan Konseling Islam',
                'Manajemen Haji dan Umrah',
                'Pendidikan Guru Madrasah Ibtidaiyah',
            ],
            'Profesi' => [
                'Pendidikan Profesi Guru',
            ],
            'S2' => [
                'Ilmu Al-Qur`an dan Tafsir',
                'Sejarah Peradaban Islam',
                'Pendidikan Agama Islam',
                'Pendidikan Bahasa Arab',
                'Ilmu Hadist',
                'Ekonomi Syari`ah',
                'Pengembangan Masyarakat Islam',
                'Hukum Keluarga (Ahwal Syakhshiyyah)',
            ],
            'S3' => [
                'Pendidikan Islam',
                'Hukum Islam',
                'Studi Islam',
            ],
        ];

        $fakultasPerProgram = [
            'D3|Ilmu Perpustakaan' => 'Adab dan Humaniora',
            'S1|Bahasa dan Sastra Arab' => 'Adab dan Humaniora',
            'S1|Sejarah Peradaban Islam (SPI)' => 'Adab dan Humaniora',
            'S1|Ilmu Perpustakaan dan Informasi Islam' => 'Adab dan Humaniora',
            'S1|Komunikasi dan Penyiaran Islam' => 'Dakwah dan Ilmu Komunikasi',
            'S1|Bimbingan Konseling Islam' => 'Dakwah dan Ilmu Komunikasi',
            'S1|Manajemen Dakwah' => 'Dakwah dan Ilmu Komunikasi',
            'S1|Pengembangan Masyarakat Islam' => 'Dakwah dan Ilmu Komunikasi',
            'S1|Manajemen Haji dan Umrah' => 'Dakwah dan Ilmu Komunikasi',
            'S1|Hukum Keluarga (Al-Ahwal asy Syakhshiyah)' => 'Syariah',
            'S1|Hukum Tatanegara (Siyasah Syar`iyyah)' => 'Syariah',
            'S1|Hukum Ekonomi Syariah (Muamalah)' => 'Syariah',
            'S1|Perbandingan Mazhab' => 'Syariah',
            'S1|Aqidah dan Filsafat Islam' => 'Ushuluddin dan Studi Agama',
            'S1|Studi Agama-agama' => 'Ushuluddin dan Studi Agama',
            'S1|Ilmu Al-Quran dan Tafsir' => 'Ushuluddin dan Studi Agama',
            'S1|Psikologi Islam' => 'Ushuluddin dan Studi Agama',
            'S1|Ilmu Hadis' => 'Ushuluddin dan Studi Agama',
            'S1|Tasawuf dan Psikoterapi' => 'Ushuluddin dan Studi Agama',
            'S1|Ekonomi Syariah' => 'Ekonomi dan Bisnis Islam',
            'S1|Akuntansi Syariah' => 'Ekonomi dan Bisnis Islam',
            'S1|Manajemen Bisnis Syariah' => 'Ekonomi dan Bisnis Islam',
            'S1|Perbankan Syariah' => 'Ekonomi dan Bisnis Islam',
            'S1|Matematika' => 'Sains dan Teknologi',
            'S1|Sistem Informasi' => 'Sains dan Teknologi',
            'S1|Pendidikan Agama Islam' => 'Tarbiyah dan Keguruan',
            'S1|Pendidikan Bahasa Arab' => 'Tarbiyah dan Keguruan',
            'S1|Manajemen Pendidikan Islam' => 'Tarbiyah dan Keguruan',
            'S1|Pendidikan Guru Madrasah Ibtidaiyah' => 'Tarbiyah dan Keguruan',
            'S1|Tadris Bahasa Inggris' => 'Tarbiyah dan Keguruan',
            'S1|Tadris Matematika' => 'Tarbiyah dan Keguruan',
            'S1|Tadris Fisika' => 'Tarbiyah dan Keguruan',
            'S1|Tadris IPS' => 'Tarbiyah dan Keguruan',
            'S1|Bimbingan dan Konseling Pendidikan Islam' => 'Tarbiyah dan Keguruan',
        ];

        foreach ($programStudi as $jenjang => $daftarNama) {
            foreach ($daftarNama as $nama) {
                $nama = trim($nama);
                ProgramStudi::updateOrCreate(
                    ['jenjang' => $jenjang, 'nama' => $nama],
                    ['fakultas' => $fakultasPerProgram["{$jenjang}|{$nama}"] ?? null],
                );
            }
        }

        $this->command?->info('Katalog program studi berhasil dibuat atau diperbarui.');
    }
}
