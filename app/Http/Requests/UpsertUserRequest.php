<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id; // route model binding

        $passwordRules = $userId
            ? ['nullable', 'string', 'min:8', 'confirmed']  // update: optional
            : ['required', 'string', 'min:8', 'confirmed']; // store: required

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => $passwordRules,
            // required dahil sa confirmed rule
            'password_confirmation' => $userId ? ['nullable', 'string', 'min:8'] : ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['super_admin', 'registrar', 'client'])],
        ];
    }
}