<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodeWisuda extends Model
{
    protected $table = 'periode_wisuda';
    const UPDATED_AT = null;

    protected $fillable = [
        'kode',
        'nama_periode',
        'tanggal_wisuda',
        'lokasi',
        'biaya',
        'min_ipk',
        'status',
    ];

    protected $casts = [
        'biaya' => 'float',
        'min_ipk' => 'float',
    ];

    public function pendaftaran()
    {
        return $this->hasMany(PendaftaranWisuda::class, 'periode_id');
    }

    public function jadwal()
    {
        return $this->hasMany(JadwalWisuda::class, 'periode_id');
    }
}
