<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Payments\Concerns\NormalizesMoneyInput;
use App\Rules\MoneyAmount;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class RegisterPaymentRequest extends FormRequest
{
    use NormalizesMoneyInput;

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->normalizeMoney(['monto']);
    }

    public function rules()
    {
        return [
            'user_id' => ['required', 'integer', 'exists:yoguinis,id'],
            'monto' => ['required', new MoneyAmount()],
            'fecha_pago' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.Carbon::now(config('app.timezone'))->toDateString(),
            ],
            'metodo' => ['required', 'in:efectivo,transferencia,otro'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'nota' => ['nullable', 'string', 'max:1000'],
            'cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'aplicar_excedente' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->boolean('aplicar_excedente') && !$this->filled('cargo_id')) {
                $validator->errors()->add('aplicar_excedente', 'Select a charge to apply the surplus to other charges.');
            }
        });
    }
}
