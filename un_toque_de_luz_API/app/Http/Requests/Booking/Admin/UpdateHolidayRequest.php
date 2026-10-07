<?php

namespace App\Http\Requests\Booking\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $holiday = $this->route('feriado');

        return [
            'fecha' => ['sometimes', 'required', 'date', Rule::unique('feriados', 'fecha')->ignore($holiday)],
            'descripcion' => 'sometimes|nullable|string|max:255',
        ];
    }
}