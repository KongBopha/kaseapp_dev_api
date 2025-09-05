<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Farm;
use App\Models\Vendor;
use App\Models\PreOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SendToVendorRequest extends FormRequest
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
          
            'vendor_id'    => 'required|exists:users,id',
            'type'         => 'required|in:accept,reject,confirm',
            'message'      => 'required|string',
            'pre_order_id' => 'required|exists:pre_orders,id',
            'reference_id' => 'required|exists:order_details,id',
        ];
    }
}
