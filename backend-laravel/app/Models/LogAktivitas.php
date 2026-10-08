<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAktivitas extends Model
{
    protected $table = 'log_aktivitas';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'aktivitas',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
