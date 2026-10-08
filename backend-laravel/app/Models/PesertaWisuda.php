<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesertaWisuda extends Model
{
    protected $table = 'peserta_wisuda';
    const UPDATED_AT = null;

    protected $fillable = [
        'pendaftaran_id',
        'nomor_peserta',
        'sesi',
        'nomor_kursi',
        'qr_code',
        'status_kehadiran',
        'waktu_hadir',
    ];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranWisuda::class, 'pendaftaran_id');
    }
}
