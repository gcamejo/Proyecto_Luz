<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Payments\Concerns\NormalizesMoneyInput;
use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;

class ApplyCreditRequest extends FormRequest
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
            'monto' => ['required', new MoneyAmount()],
        ];
    }
}
