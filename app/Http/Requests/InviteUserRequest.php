<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'role_id' => 'required|exists:roles,id'
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.max' => 'El email no puede exceder 255 caracteres.',
            'role_id.required' => 'Debes seleccionar un rol.',
            'role_id.exists' => 'El rol seleccionado no es válido.'
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email))
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Verificar que no sea rol de owner
            if ($this->role_id) {
                $role = \App\Models\Role::find($this->role_id);
                if ($role && $role->name === 'owner') {
                    $validator->errors()->add('role_id', 'No se puede asignar el rol de propietario.');
                }
            }
        });
    }
}
