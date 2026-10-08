<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BerkasWisuda extends Model
{
    protected $table = 'berkas_wisuda';
    public $timestamps = false;

    protected $fillable = [
        'pendaftaran_id',
        'jenis_berkas_id',
        'nama_file',
        'file_path',
        'status',
        'catatan',
        'tanggal_upload',
        'tanggal_verifikasi',
    ];

    public function pendaftaran()
    {
        return $this->belongsTo(PendaftaranWisuda::class, 'pendaftaran_id');
    }

    public function jenisBerkas()
    {
        return $this->belongsTo(JenisBerkas::class, 'jenis_berkas_id');
    }
}
