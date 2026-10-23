<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consentimiento extends Model
{
    public $timestamps = false;

    protected $table = 'consentimientos';

    protected $fillable = [
        'user_id',
        'tipo',
        'version',
        'aceptado_en',
    ];

    protected $casts = [
        'aceptado_en' => 'datetime',
    ];

    public function yoguini()
    {
        return $this->belongsTo(Yoguini::class, 'user_id');
    }
}