<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->role === UserRole::Admin;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user),
            ],

            'designation_id' => [
                'required',
                'integer',
                Rule::exists('designations', 'id')
                    ->where('is_active', true),
            ],

            'unit' => ['required', 'string', Rule::in(config('scheduler.user_units'))],

            'role' => [
                'required',
                Rule::enum(UserRole::class),
            ],

            'password' => [
                'nullable',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'designation_id' => __('Designation'),
            'unit' => __('Unit'),
        ];
    }
}
