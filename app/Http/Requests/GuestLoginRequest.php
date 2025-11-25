<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GuestLoginRequest extends FormRequest
{
    use ApiResponse;
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
     * @return array<string, 
     */
    public function rules(): array
    {
        return [
            'fcmToken' => 'nullable|string',
            'languageCode' => 'nullable|string|size:2',
            'session_type' => ['nullable', 'in:text, voice, video'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->first(); 
        
        throw new HttpResponseException(
            $this->errorResponse($errors, 422)
        );
    }
}
