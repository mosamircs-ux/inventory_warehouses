<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'location'     => ['required', 'string', 'max:255'],
            'capacity'     => ['nullable', 'integer', 'min:0'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'email'        => ['nullable', 'string', 'email', 'max:255'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المستودع مطلوب',
            'location.required' => 'موقع المستودع مطلوب',
            'capacity.integer' => 'السعة يجب أن تكون رقم صحيح',
            'email.email' => 'يرجى إدخال بريد إلكتروني صالح',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'فشل التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
