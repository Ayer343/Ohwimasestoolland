<?php

namespace App\Http\Requests;

use App\Support\PaymentProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;

class TogglePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware already guards this; add an explicit gate for defence in depth.
        return $this->user()?->can('manage-payments') ?? false;
    }

    public function rules(): array
    {
        return [
            'gateway' => ['required', 'string', PaymentProviderRegistry::validationRule()],
            'enabled' => ['required', 'boolean'],
        ];
    }
}