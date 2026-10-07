<?php

namespace App\Http\Requests\Booking\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ClassIndexRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'fecha' => 'sometimes|date_format:Y-m-d',
            'ciclo_id' => 'sometimes|integer|exists:ciclos,id',
        ];
    }
}