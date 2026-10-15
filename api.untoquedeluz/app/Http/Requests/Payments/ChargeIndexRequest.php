<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class ChargeIndexRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'periodo' => ['nullable', 'date_format:Y-m'],
            'estado' => ['nullable', 'in:pendiente,parcial,pagado,anulado'],
            'ciclo_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
