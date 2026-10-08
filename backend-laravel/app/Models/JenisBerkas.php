<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisBerkas extends Model
{
    protected $table = 'jenis_berkas';
    public $timestamps = false;

    protected $fillable = [
        'nama',
        'keterangan',
        'wajib',
        'is_pembayaran',
        'aktif',
        'urutan',
    ];

    protected $casts = [
        'wajib' => 'boolean',
        'is_pembayaran' => 'boolean',
        'aktif' => 'boolean',
        'urutan' => 'integer',
    ];

    public function berkas()
    {
        return $this->hasMany(BerkasWisuda::class, 'jenis_berkas_id');
    }
}
