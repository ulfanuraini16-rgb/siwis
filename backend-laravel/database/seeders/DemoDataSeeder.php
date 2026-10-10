<?php

namespace Database\Seeders;

use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /** Seed one optional student account for local demonstrations. */
    public function run(): void
    {
        $email = 'mahasiswa@siwis.test';
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'nama' => 'Mahasiswa Demo',
                'role' => 'mahasiswa',
                'password' => Hash::make('mahasiswa123'),
            ],
        );

        if ($user->role !== 'mahasiswa') {
            $this->command?->warn('Akun demo tidak dibuat: email demo telah digunakan role lain.');
            return;
        }

        Mahasiswa::firstOrCreate(
            ['nim' => '2317020138'],
            [
                'user_id' => $user->id,
                'nama' => 'Mahasiswa Demo',
                'email' => $email,
                'fakultas' => 'Teknik',
                'program_studi' => 'Teknik Informatika',
                'ipk' => 3.50,
                'status_kelulusan' => 'Belum Lulus',
            ],
        );

        $this->command?->info('Data mahasiswa demo tersedia (akun tidak ditimpa jika sudah ada).');
    }
}
