<?php

namespace App\Http\Requests\Developer\Billing;

use Illuminate\Foundation\Http\FormRequest;

class ExportBillingDataRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->type === \App\Models\User::TYPE_DEVELOPER;
    }

    public function rules()
    {
        return [
            'export_type' => 'required|in:invoices,agreements,payments',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'nullable|in:csv,excel,pdf',
        ];
    }
}