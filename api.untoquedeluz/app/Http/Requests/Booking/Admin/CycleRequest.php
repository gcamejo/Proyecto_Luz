<?php

namespace App\Http\Requests\Booking\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CycleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $dateEndRule = $this->isMethod('post') ? '|after_or_equal:fecha_inicio' : '';

        return [
            'nombre' => $required.'|string|max:255',
            'fecha_inicio' => $required.'|date',
            'fecha_fin' => $required.'|date'.$dateEndRule,
            'clases_por_semana' => $required.'|integer|between:1,7',
            'horario_ids' => $this->isMethod('post') ? 'required|array|min:1' : 'sometimes|required|array|min:1',
            'horario_ids.*' => 'required|integer|distinct|exists:horarios,id',
            'precio' => $required.'|numeric|min:0|max:99999999.99',
            'activo' => 'sometimes|boolean',
        ];
    }
}