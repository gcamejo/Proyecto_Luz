<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class Yoguini extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens; 

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'apellido',
        'direccion',
        'numero',
        'telefono',
        'fechaNacimiento',
        'email',
        'password',
        'perfil'
    ];

    protected $hidden = ['password'];

    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class, 'user_id');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'user_id');
    }

    public function recuperaciones()
    {
        return $this->hasMany(Recuperacion::class, 'user_id');
    }
}
