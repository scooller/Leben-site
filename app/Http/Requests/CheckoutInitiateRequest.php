<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutInitiateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plant_id' => ['required', 'integer', 'exists:plants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'gateway' => ['required', 'string', 'in:transbank,mercadopago,manual'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'phone' => ['required', 'string', 'max:20'],
            'rut' => [
                'required',
                'string',
                'max:12',
            ],
            'session_token' => ['nullable', 'string', 'max:64', 'required_if:gateway,manual'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'El correo electrónico ya está registrado en otra cuenta.',
            'rut.unique' => 'El RUT ya está registrado en otra cuenta.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plant_id' => 'planta',
            'quantity' => 'cantidad',
            'gateway' => 'pasarela de pago',
            'name' => 'nombre completo',
            'email' => 'correo electronico',
            'phone' => 'telefono',
            'rut' => 'RUT',
            'session_token' => 'token de reserva',
        ];
    }
}
