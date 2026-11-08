<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\PaymentMethod;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isGuest = is_null($this->user());

        return [
            'idempotency_key'                 => ['required', 'string', 'max:255'],
            'guest_email'                     => [$isGuest ? 'required' : 'nullable', 'email', 'max:255'],
            'guest_phone'                     => [$isGuest ? 'required' : 'nullable', 'string', 'max:30'],
            'items'                           => ['required', 'array', 'min:1'],
            'items.*.product_variant_id'      => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'                => ['required', 'integer', 'min:1', 'max:1000'],
            'shipping_address'                => ['required', 'array'],
            'shipping_address.recipient_name' => ['required', 'string', 'max:255'],
            'shipping_address.phone'          => ['required', 'string', 'max:30'],
            'shipping_address.address_line1'  => ['required', 'string', 'max:255'],
            'shipping_address.address_line2'  => ['nullable', 'string', 'max:255'],
            'shipping_address.city'           => ['required', 'string', 'max:100'],
            'shipping_address.province'       => ['required', 'string', 'max:100'],
            'shipping_address.postal_code'    => ['required', 'string', 'max:20'],
            'shipping_address.country'        => ['nullable', 'string', 'max:10'],
            'shipping_method'                 => ['nullable', 'string', 'max:50'],
            'payment_method'                  => ['sometimes', Rule::enum(PaymentMethod::class)],
        ];
    }
}
