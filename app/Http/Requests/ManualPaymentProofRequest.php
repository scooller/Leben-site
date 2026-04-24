<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManualPaymentProofRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,heic,heif', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proof.required' => 'Debes adjuntar un comprobante de pago.',
            'proof.file' => 'El comprobante enviado no es un archivo válido.',
            'proof.uploaded' => 'No se pudo subir el comprobante. Verifica que no supere 5 MB e inténtalo nuevamente.',
            'proof.mimes' => 'El comprobante debe estar en formato JPG, JPEG, PNG, HEIC, HEIF o PDF.',
            'proof.max' => 'El comprobante no puede superar los 5 MB.',
            'notes.max' => 'Las notas no pueden superar los 1000 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'proof' => 'comprobante de pago',
            'notes' => 'notas',
        ];
    }
}
