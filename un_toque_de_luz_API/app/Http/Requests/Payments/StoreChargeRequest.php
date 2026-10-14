<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Payments\Concerns\NormalizesMoneyInput;
use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;

class StoreChargeRequest extends FormRequest
{
    use NormalizesMoneyInput;

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->normalizeMoney(['monto_original']);
        $period = $this->input('periodo');
        if (is_string($period) && preg_match('/^(\d{4})-(0[1-9]|1[0-2])(-\d{2})?$/', $period, $parts)) {
            $this->merge(['periodo' => $parts[1].'-'.$parts[2].'-01']);
        }
    }

    public function rules()
    {
        return [
            'user_id' => ['required', 'integer', 'exists:yoguinis,id'],
            'periodo' => ['required', 'date_format:Y-m-d'],
            'concepto' => ['required', 'string', 'max:255'],
            'monto_original' => ['required', new MoneyAmount()],
            'vence_el' => ['nullable', 'date_format:Y-m-d'],
            'ciclo_id' => ['nullable', 'integer', 'exists:ciclos,id'],
        ];
    }
}
