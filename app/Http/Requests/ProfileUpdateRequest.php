<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * El correo no se cambia desde aquí: es con el que entra (también por
     * Google) y al que llegan los recibos de Stripe.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?\d{10,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Escribe un teléfono para contactarte.',
            'phone.regex' => 'El teléfono debe tener 10 dígitos (o lada internacional con +, hasta 15).',
        ];
    }

    /** "55 1234-5678" y "(55) 1234 5678" se guardan como 5512345678; "+1 ..." conserva el +. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->phone)) {
            $mas = str_starts_with(trim($this->phone), '+') ? '+' : '';
            $this->merge(['phone' => $mas.preg_replace('/\D/', '', $this->phone)]);
        }
    }
}
