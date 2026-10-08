<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalWisuda extends Model
{
    protected $table = 'jadwal_wisuda';
    public $timestamps = false;

    protected $fillable = [
        'periode_id',
        'sesi',
        'tanggal',
        'waktu',
        'lokasi',
        'keterangan',
    ];

    public function periode()
    {
        return $this->belongsTo(PeriodeWisuda::class, 'periode_id');
    }
}
