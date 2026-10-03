{{-- resources/views/admin/invoices/archive-json.blade.php --}}
{
    "archive": {
        "id": {{ $archive->id }},
        "invoice_number": "{{ $archive->invoice_number }}",
        "original_invoice_id": {{ $archive->original_invoice_id ?? 'null' }},
        "property": {
            "id": {{ $archive->property_id ?? 'null' }},
            "name": "{{ $archive->property_name }}"
        },
        "landlord": {
            "id": {{ $archive->landlord_id ?? 'null' }},
            "name": "{{ $archive->landlord_name }}"
        },
        "period": {
            "code": "{{ $archive->period }}",
            "month": "{{ $archive->month_name }}",
            "due_date": "{{ $archive->due_date ? \Carbon\Carbon::parse($archive->due_date)->format('Y-m-d') : 'null' }}"
        },
        "financial": {
            "amount": {{ (float) $archive->amount }},
            "penalty_amount": {{ (float) $archive->penalty_amount }},
            "total_amount": {{ (float) $archive->total_amount }},
            "paid_amount": {{ (float) $archive->paid_amount }},
            "balance": {{ (float) $archive->balance }}
        },
        "status": "{{ $archive->status }}",
        "payment": {
            "method": "{{ $archive->payment_method }}",
            "reference": "{{ $archive->payment_reference }}",
            "date": "{{ $archive->payment_date ? \Carbon\Carbon::parse($archive->payment_date)->format('Y-m-d') : 'null' }}"
        },
        "bulk_payment": {
            "is_bulk": {{ $archive->is_bulk_payment ? 'true' : 'false' }},
            "covers_periods": {!! json_encode($archive->covers_periods) !!},
            "bulk_coverage_start": "{{ $archive->bulk_coverage_start }}",
            "bulk_coverage_end": "{{ $archive->bulk_coverage_end }}"
        },
        "archive": {
            "type": "{{ $archive->archive_type }}",
            "deleted_at": "{{ $archive->deleted_at ? \Carbon\Carbon::parse($archive->deleted_at)->toISOString() : 'null' }}",
            "deleted_by": {
                "id": {{ $archive->deleted_by ?? 'null' }},
                "name": "{{ $archive->deleted_by_name }}"
            },
            "deletion_reason": "{{ addslashes($archive->deletion_reason) }}",
            "deletion_ip": "{{ $archive->deletion_ip }}",
            "user_agent": "{{ addslashes($archive->deletion_user_agent) }}"
        },
        "original_creation": {
            "created_at": "{{ $archive->original_created_at ? \Carbon\Carbon::parse($archive->original_created_at)->toISOString() : 'null' }}",
            "created_by": {{ $archive->original_created_by ?? 'null' }},
            "updated_at": "{{ $archive->original_updated_at ? \Carbon\Carbon::parse($archive->original_updated_at)->toISOString() : 'null' }}",
            "updated_by": {{ $archive->original_updated_by ?? 'null' }}
        },
        "system_settings": {
            "grace_period_days": {{ $archive->grace_period_days ?? 'null' }},
            "late_payment_percentage": {{ (float) $archive->late_payment_percentage ?? 'null' }},
            "fixed_penalty_amount": {{ (float) $archive->fixed_penalty_amount ?? 'null' }}
        },
        "description": "{{ addslashes($archive->description) }}",
        "notes": "{{ addslashes($archive->notes) }}",
        "metadata": {!! json_encode($archive->metadata) !!}
    },
    "export_info": {
        "generated_at": "{{ now()->toISOString() }}",
        "generated_by": "{{ auth()->user()->name }}",
        "generated_by_id": {{ auth()->id() }},
        "format_version": "1.0"
    }
}