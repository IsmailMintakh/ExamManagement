<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('super-admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:schools,code'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg', 'max:2048'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'principal_signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'school_stamp' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'exam_officer_signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'exam_officer_name' => ['nullable', 'string', 'max:120'],
            'principal_name' => ['nullable', 'string', 'max:120'],
            // Principal login account — optional but recommended.
            // Leave blank to skip; the school can be created and a Principal
            // user added later via Users → Add User. When supplied, all three
            // are required as a unit and the email must be globally unique
            // across the users table.
            'principal_email' => ['nullable', 'required_with:principal_password', 'email', 'max:255', 'unique:users,email'],
            'principal_password' => ['nullable', 'required_with:principal_email', 'string', 'min:8', 'confirmed'],
            'is_main' => ['boolean'],
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
