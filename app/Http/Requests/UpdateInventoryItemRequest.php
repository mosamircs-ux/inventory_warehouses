<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $inventoryItemId = $this->route('inventory')->id;
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => [
                'sometimes', 
                'required', 
                'string', 
                'max:100',
                Rule::unique('inventory_items', 'sku')->ignore($inventoryItemId)
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'اسم المنتج مطلوب',
            'name.string' => 'اسم المنتج يجب أن يكون نص',
            'name.max' => 'اسم المنتج يجب أن يكون أقل من 255 حرف',
            'min_stock_level.integer' => 'الحد الأدنى للمخزون يجب أن يكون رقم',
            'min_stock_level.min' => 'الحد الأدنى للمخزون يجب أن يكون أكبر من 0',
            'description.string' => 'الوصف يجب أن يكون نص',
            'description.max' => 'الوصف يجب أن يكون أقل من 1000 حرف',
            'sku.required' => 'رمز SKU مطلوب',
            'sku.string' => 'رمز SKU يجب أن يكون نص',
            'sku.max' => 'رمز SKU يجب أن يكون أقل من 100 حرف',
            'sku.unique' => 'رمز SKU موجود مسبقاً',
            'price.required' => 'السعر مطلوب',
            'price.numeric' => 'السعر يجب أن يكون رقم',
            'price.min' => 'السعر يجب أن يكون أكبر من 0',
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
