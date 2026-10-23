<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminStoreYoguiniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->perfil === 'Admin';
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:30',
            'apellido' => 'required|string|max:30',
            'direccion' => 'required|string|max:30',
            'numero' => 'required|integer|min:0',
            'telefono' => 'required|string|max:30',
            'fechaNacimiento' => 'required|date',
            'email' => 'required|email|max:100|unique:yoguinis,email',
            'password' => 'required|string|min:8|max:100',
        ];
    }
}