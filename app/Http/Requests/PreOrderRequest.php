<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;  
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'qty' => 'required|numeric|min:0.01',
            'location' => 'required|string',
            'note_text' => 'nullable|string',
            'delivery_date' => 'required|date',
            'recurring_schedule' => 'nullable|string',
            'input_product_name' => 'nullable|string|max:255'
        ];
    }
}

