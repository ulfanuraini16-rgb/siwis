<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SiwisHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $identitas = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if (!$identitas || !$password) {
            return response()->json(['message' => 'Email/NIM dan password wajib diisi.'], 400);
        }

        $user = User::query()
            ->select('users.*')
            ->leftJoin('mahasiswa as m', 'm.user_id', '=', 'users.id')
            ->where('users.email', $identitas)
            ->orWhere('m.nim', $identitas)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Email/NIM atau password salah.'], 401);
        }

        // Generate Sanctum token
        $token = $user->createToken('siwis_token')->plainTextToken;

        SiwisHelper::logActivity($user->id, "{$user->nama} ({$user->role}) login");

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'nama' => $user->nama,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'nama' => $user->nama,
            ],
            'kampus' => env('NAMA_KAMPUS', 'Universitas Contoh Nusantara'),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $passwordLama = (string) $request->input('password_lama', '');
        $passwordBaru = (string) $request->input('password_baru', '');

        if (!$passwordBaru || strlen($passwordBaru) < 6) {
            return response()->json(['message' => 'Password baru minimal 6 karakter.'], 400);
        }

        if (!Hash::check($passwordLama, $user->password)) {
            return response()->json(['message' => 'Password lama tidak sesuai.'], 400);
        }

        $user->password = Hash::make($passwordBaru);
        $user->save();

        SiwisHelper::logActivity($user->id, "{$user->nama} mengganti password");

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $nama = trim((string) $request->input('nama', ''));
        $email = trim((string) $request->input('email', ''));

        if (!$nama || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Nama dan email yang valid wajib diisi.'], 400);
        }

        $duplicate = User::where('email', $email)->where('id', '<>', $user->id)->exists();
        if ($duplicate) {
            return response()->json(['message' => 'Email sudah digunakan akun lain.'], 400);
        }

        $user->nama = $nama;
        $user->email = $email;
        $user->save();

        return response()->json(['message' => 'Profil berhasil diperbarui.']);
    }
}
