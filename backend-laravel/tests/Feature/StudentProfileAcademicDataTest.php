<?php

namespace Tests\Feature;

use App\Models\Mahasiswa;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentProfileAcademicDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_faculty_and_program_study_from_catalog(): void
    {
        $user = $this->createStudent();
        ProgramStudi::create([
            'jenjang' => 'S1',
            'nama' => 'Sistem Informasi',
            'fakultas' => 'Sains dan Teknologi',
        ]);

        $response = $this->actingAs($user)->putJson('/api/student/profil', [
            'nim' => '1234567891',
            'nama' => 'Nama Baru',
            'email' => 'updated@example.test',
            'no_hp' => '081234567890',
            'judul_skripsi' => 'Judul tugas akhir',
            'fakultas' => 'Sains dan Teknologi',
            'program_studi' => 'S1 - Sistem Informasi',
            'ipk' => 3.8,
            'tahun_masuk' => 2021,
            'tahun_lulus' => 2025,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('mahasiswa', [
            'user_id' => $user->id,
            'nim' => '1234567891',
            'nama' => 'Nama Baru',
            'email' => 'updated@example.test',
            'fakultas' => 'Sains dan Teknologi',
            'program_studi' => 'S1 - Sistem Informasi',
            'ipk' => 3.8,
            'tahun_masuk' => 2021,
            'tahun_lulus' => 2025,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nama' => 'Nama Baru',
            'email' => 'updated@example.test',
        ]);
    }

    public function test_student_cannot_select_a_program_study_from_another_faculty(): void
    {
        $user = $this->createStudent();
        ProgramStudi::create([
            'jenjang' => 'S1',
            'nama' => 'Sistem Informasi',
            'fakultas' => 'Sains dan Teknologi',
        ]);

        $response = $this->actingAs($user)->putJson('/api/student/profil', [
            'nim' => '1234567890',
            'nama' => 'Mahasiswa Uji',
            'email' => 'student@example.test',
            'no_hp' => '081234567890',
            'judul_skripsi' => 'Judul tugas akhir',
            'fakultas' => 'Fakultas yang berbeda',
            'program_studi' => 'S1 - Sistem Informasi',
            'ipk' => 3.5,
            'tahun_masuk' => 2020,
            'tahun_lulus' => 2024,
        ]);

        $response->assertBadRequest();
        $this->assertDatabaseHas('mahasiswa', [
            'user_id' => $user->id,
            'fakultas' => 'Fakultas lama',
            'program_studi' => 'Data lama',
        ]);
    }

    public function test_student_cannot_use_another_students_nim(): void
    {
        $user = $this->createStudent();
        $otherUser = User::create([
            'email' => 'other@example.test',
            'nama' => 'Mahasiswa Lain',
            'password' => Hash::make('password123'),
            'role' => 'mahasiswa',
        ]);
        Mahasiswa::create([
            'user_id' => $otherUser->id,
            'nim' => '1234567891',
            'nama' => 'Mahasiswa Lain',
            'email' => 'other@example.test',
            'fakultas' => 'Fakultas lama',
            'program_studi' => 'Data lama',
            'ipk' => 3.5,
            'status_kelulusan' => 'Lulus',
        ]);

        $response = $this->actingAs($user)->putJson('/api/student/profil', [
            'nim' => '1234567891',
            'nama' => 'Mahasiswa Uji',
            'email' => 'student@example.test',
            'no_hp' => '081234567890',
            'judul_skripsi' => 'Judul tugas akhir',
            'fakultas' => 'Fakultas lama',
            'program_studi' => 'Data lama',
            'ipk' => 3.5,
            'tahun_masuk' => 2020,
            'tahun_lulus' => 2024,
        ]);

        $response->assertBadRequest();
        $this->assertDatabaseHas('mahasiswa', ['user_id' => $user->id, 'nim' => '1234567890']);
    }

    private function createStudent(): User
    {
        $user = User::create([
            'email' => 'student@example.test',
            'nama' => 'Mahasiswa Uji',
            'password' => Hash::make('password123'),
            'role' => 'mahasiswa',
        ]);

        Mahasiswa::create([
            'user_id' => $user->id,
            'nim' => '1234567890',
            'nama' => 'Mahasiswa Uji',
            'email' => 'student@example.test',
            'no_hp' => '081111111111',
            'fakultas' => 'Fakultas lama',
            'program_studi' => 'Data lama',
            'ipk' => 3.5,
            'judul_skripsi' => 'Judul lama',
            'status_kelulusan' => 'Lulus',
            'tahun_masuk' => 2020,
            'tahun_lulus' => 2024,
        ]);

        return $user;
    }
}
