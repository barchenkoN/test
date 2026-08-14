<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimPromoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9]{6,12}$/D',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Введіть промокод.',
            'code.string' => 'Промокод має бути текстом.',
            'code.regex' => 'Промокод має містити від 6 до 12 латинських літер або цифр.',
        ];
    }
}
