<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pembayaran extends Model
{
    protected $table = 'pembayaran';
    public $timestamps = false;

    protected $fillable = [
        'pendaftaran_id',
        'nominal',
        'bukti_pembayaran',
        'status',
        'tanggal_pembayaran',
    ];

    protected $casts = [
        'nominal' => 'float',
    ];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranWisuda::class, 'pendaftaran_id');
    }
}
