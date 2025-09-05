<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Farm;
use App\Models\Vendor;
use App\Models\PreOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SendToFarmersRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'farm_id'      => 'required|exists:farm_id',
            'type'         => 'required|in:pre_order',
            'message'      => 'required|string',
            'pre_order_id' => 'required|exists:pre_orders,id',
            'reference_id' => 'nullable|exists:order_details,id',
        ];
    }
}
