<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class JournalRequest extends FormRequest
{
    use ApiResponse;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('mood')) {
            $this->merge([
                'mood_snapshot' => $this->input('mood')
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:150',
            'content' => 'required_without:audio_path|string',
            
            // Validate the INTERNAL name (mood_snapshot)
            'mood_snapshot' => 'nullable|string|max:50', 
            
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:30',
            'audio_path' => 'nullable|string', 
            'audio_duration' => 'nullable|integer',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            $this->errorResponse($validator->errors()->first(), 422)
        );
    }
}
