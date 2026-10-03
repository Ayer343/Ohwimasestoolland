<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractorProfile extends Model
{
    use SoftDeletes;

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id',
        'company_name',
        'registration_number',
        'tin_number',
        'license_number',
        'phone',
        'email',
        'address',
        'website',
        'specializations',
        'certifications',
        'logo',
        'status'
    ];

    protected $casts = [
        'specializations' => 'array',
        'certifications' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contracts()
    {
        return $this->hasMany(ContractorContract::class);
    }

    public function activeContracts()
    {
        return $this->contracts()->where('status', 'active');
    }

    public function completedContracts()
    {
        return $this->contracts()->where('status', 'completed');
    }

    // Accessors
    public function getTotalWorkersAttribute()
    {
        return ConstructionWorker::whereHas('contract', function($query) {
            $query->whereHas('contractorContracts', function($q) {
                $q->where('contractor_profile_id', $this->id);
            });
        })->count();
    }

    public function getActiveWorkersAttribute()
    {
        return ConstructionWorker::whereHas('contract', function($query) {
            $query->whereHas('contractorContracts', function($q) {
                $q->where('contractor_profile_id', $this->id)
                  ->where('status', 'active');
            });
        })->where('status', 'active')->count();
    }

    public function getSpecializationsListAttribute()
    {
        if (!$this->specializations) {
            return 'No specializations listed';
        }
        return implode(', ', $this->specializations);
    }

    // Helper Methods
    public function canManageContract(ConstructionContract $contract)
    {
        return $this->contracts()
            ->where('construction_contract_id', $contract->id)
            ->where('status', 'active')
            ->exists();
    }

    public function getAssignedWorkers(ConstructionContract $contract)
    {
        return ConstructionWorker::whereHas('contract', function($query) use ($contract) {
            $query->where('id', $contract->id);
        })->get();
    }
}