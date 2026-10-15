<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Payments\Concerns\NormalizesMoneyInput;
use App\Rules\MoneyAmount;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

class ChargeDiscountRequest extends FormRequest
{
    use NormalizesMoneyInput;

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->normalizeMoney(['descuento']);
    }

    public function rules()
    {
        return [
            'descuento' => ['required', new MoneyAmount(true)],
            'motivo_descuento' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $discount = $this->input('descuento');
            if (is_string($discount) && preg_match('/^\d{1,10}(\.\d{1,2})?$/', $discount)
                && Money::cents($discount) > 0
                && trim((string) $this->input('motivo_descuento')) === '') {
                $validator->errors()->add('motivo_descuento', 'A reason is required for a discount.');
            }
        });
    }
}
