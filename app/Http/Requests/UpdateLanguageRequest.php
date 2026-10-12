<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Traits\ApiResponse;
use App\Support\Language;

class UpdateLanguageRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // One of the app languages (Language::SUPPORTED); ISO aliases like 'bn' are accepted.
            'languageCode' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!Language::isSupported($value)) {
                    $fail('Unsupported language.');
                }
            }],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            $this->errorResponse($validator->errors()->first(), 422)
        );
    }
}