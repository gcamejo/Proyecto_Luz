<?php

namespace App\Http\Requests\Booking\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $optional = $this->isMethod('post') ? '' : 'sometimes|';

        return [
            'dia_semana' => $required.'|integer|between:0,6',
            'hora_inicio' => $required.'|date_format:H:i',
            'duracion_min' => $required.'|integer|min:1|max:1440',
            'nivel' => $optional.'nullable|string|max:255',
            'profesor' => $optional.'nullable|string|max:255',
            'cupo' => $required.'|integer|min:1|max:65535',
            'activo' => 'sometimes|boolean',
        ];
    }
}