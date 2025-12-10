<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Allow anyone to try to login
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string',
        ];
    }
    
    /**
     * Optional: Customize error messages
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'password.required' => 'Password is required.',
        ];
    }
}