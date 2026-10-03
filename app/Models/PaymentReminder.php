<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_billing_record_id',
        'sent_to',
        'sent_by',
        'reminder_type',
        'sent_at',
        'notes',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * Relationship with AdminBillingRecord
     */
    public function adminBillingRecord()
    {
        return $this->belongsTo(AdminBillingRecord::class);
    }

    /**
     * Relationship with sender (User)
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}