<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendaftaranWisuda extends Model
{
    protected $table = 'pendaftaran_wisuda';

    protected $fillable = [
        'mahasiswa_id',
        'periode_id',
        'tanggal_daftar',
        'status',
        'catatan',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function periode()
    {
        return $this->belongsTo(PeriodeWisuda::class, 'periode_id');
    }

    public function berkas()
    {
        return $this->hasMany(BerkasWisuda::class, 'pendaftaran_id');
    }

    public function pembayaran()
    {
        return $this->hasOne(Pembayaran::class, 'pendaftaran_id');
    }

    public function peserta()
    {
        return $this->hasOne(PesertaWisuda::class, 'pendaftaran_id');
    }
}
