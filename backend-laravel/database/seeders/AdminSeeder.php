<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('ADMIN_EMAIL', ''));
        $password = (string) env('ADMIN_PASSWORD', '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('ADMIN_EMAIL harus diatur sebagai alamat email valid di backend-laravel/.env.');
        }

        if (strlen($password) < 12) {
            throw new RuntimeException('ADMIN_PASSWORD harus diatur di backend-laravel/.env dan minimal 12 karakter.');
        }

        $existing = User::where('email', $email)->first();
        if ($existing && $existing->role !== 'admin') {
            throw new RuntimeException('ADMIN_EMAIL sudah digunakan akun non-admin; gunakan email lain.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'nama' => 'Administrator SIWIS',
                'role' => 'admin',
                'password' => Hash::make($password),
            ],
        );

        $this->command?->info('Akun admin dari ADMIN_EMAIL berhasil dibuat atau diperbarui.');
    }
}
