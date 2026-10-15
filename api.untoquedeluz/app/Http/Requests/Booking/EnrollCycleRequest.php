<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class EnrollCycleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'horario_ids' => 'required|array|min:1',
            'horario_ids.*' => 'required|integer|distinct|exists:horarios,id',
        ];
    }
}