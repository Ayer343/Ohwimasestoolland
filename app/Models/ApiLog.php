<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'api_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'method',
        'endpoint',
        'status_code',
        'response_time',
        'ip_address',
        'user_agent',
        'user_id',
        'request_data',
        'response_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'response_time' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user associated with the API log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for successful responses (2xx status codes).
     */
    public function scopeSuccessful($query)
    {
        return $query->whereBetween('status_code', [200, 299]);
    }

    /**
     * Scope for client errors (4xx status codes).
     */
    public function scopeClientErrors($query)
    {
        return $query->whereBetween('status_code', [400, 499]);
    }

    /**
     * Scope for server errors (5xx status codes).
     */
    public function scopeServerErrors($query)
    {
        return $query->whereBetween('status_code', [500, 599]);
    }

    /**
     * Scope for specific HTTP method.
     */
    public function scopeMethod($query, $method)
    {
        return $query->where('method', strtoupper($method));
    }

    /**
     * Scope for today's logs.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for logs from a specific IP.
     */
    public function scopeFromIp($query, $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Scope for logs by endpoint pattern.
     */
    public function scopeEndpointLike($query, $pattern)
    {
        return $query->where('endpoint', 'LIKE', "%{$pattern}%");
    }

    /**
     * Check if the response was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    /**
     * Check if the response was an error.
     */
    public function isError(): bool
    {
        return $this->status_code >= 400;
    }

    /**
     * Get the formatted response time.
     */
    public function getFormattedResponseTimeAttribute(): string
    {
        return number_format($this->response_time, 2) . ' ms';
    }

    /**
     * Get the request method with color for display.
     */
    public function getMethodColorAttribute(): string
    {
        return match($this->method) {
            'GET'    => 'text-blue-600',
            'POST'   => 'text-green-600',
            'PUT'    => 'text-yellow-600',
            'PATCH'  => 'text-yellow-600',
            'DELETE' => 'text-red-600',
            default  => 'text-gray-600',
        };
    }

    /**
     * Get the status code with color for display.
     */
    public function getStatusCodeColorAttribute(): string
    {
        if ($this->isSuccessful()) {
            return 'text-green-600';
        }

        if ($this->status_code >= 400 && $this->status_code < 500) {
            return 'text-yellow-600';
        }

        if ($this->status_code >= 500) {
            return 'text-red-600';
        }

        return 'text-gray-600';
    }

    /**
     * Get the request data as a pretty JSON string.
     */
    public function getPrettyRequestDataAttribute(): ?string
    {
        return $this->request_data ? json_encode($this->request_data, JSON_PRETTY_PRINT) : null;
    }

    /**
     * Get the response data as a pretty JSON string.
     */
    public function getPrettyResponseDataAttribute(): ?string
    {
        return $this->response_data ? json_encode($this->response_data, JSON_PRETTY_PRINT) : null;
    }

    public function developerSetting(): BelongsTo
{
    return $this->belongsTo(DeveloperSetting::class, 'developer_setting_id');
}
}
