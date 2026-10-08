<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mahasiswa extends Model
{
    protected $table = 'mahasiswa';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'nim',
        'nama',
        'email',
        'no_hp',
        'fakultas',
        'program_studi',
        'ipk',
        'judul_skripsi',
        'status_kelulusan',
        'tahun_masuk',
        'tahun_lulus',
    ];

    protected $casts = [
        'ipk' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pendaftaran()
    {
        return $this->hasMany(PendaftaranWisuda::class, 'mahasiswa_id');
    }
}
