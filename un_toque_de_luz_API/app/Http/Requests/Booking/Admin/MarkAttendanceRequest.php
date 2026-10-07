<?php

namespace App\Http\Requests\Booking\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MarkAttendanceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return ['estado' => 'required|in:asistio,falto'];
    }
}