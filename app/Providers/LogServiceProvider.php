<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Log\Events\MessageLogged;

class LogServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Listen to Laravel log events and store them in database
        Log::listen(function (MessageLogged $messageLogged) {
            $level = $messageLogged->level;
            $message = $messageLogged->message;
            $context = $messageLogged->context;
            
            // Determine source based on context
            $source = $this->determineSource($context);
            
            // Create system log entry
            \App\Models\SystemLog::createLog(
                $level,
                $message,
                $context,
                [
                    'source' => $source,
                    'stack_trace' => $context['exception']->getTrace() ?? null,
                    'tags' => $this->extractTags($context)
                ]
            );
        });
    }

    private function determineSource($context)
    {
        if (isset($context['source'])) {
            return $context['source'];
        }
        
        if (isset($context['channel'])) {
            return match($context['channel']) {
                'payment' => \App\Models\SystemLog::SOURCE_PAYMENT,
                'sms' => \App\Models\SystemLog::SOURCE_SMS,
                'email' => \App\Models\SystemLog::SOURCE_EMAIL,
                'database' => \App\Models\SystemLog::SOURCE_DATABASE,
                default => \App\Models\SystemLog::SOURCE_APPLICATION
            };
        }
        
        // Check for specific patterns in message
        $message = $context['message'] ?? '';
        if (str_contains($message, 'payment') || str_contains($message, 'Payment')) {
            return \App\Models\SystemLog::SOURCE_PAYMENT;
        }
        
        if (str_contains($message, 'database') || str_contains($message, 'Database')) {
            return \App\Models\SystemLog::SOURCE_DATABASE;
        }
        
        if (str_contains($message, 'api') || str_contains($message, 'API')) {
            return \App\Models\SystemLog::SOURCE_API;
        }
        
        return \App\Models\SystemLog::SOURCE_APPLICATION;
    }

    private function extractTags($context)
    {
        $tags = [];
        
        if (isset($context['tags']) && is_array($context['tags'])) {
            $tags = array_merge($tags, $context['tags']);
        }
        
        // Add environment tag
        $tags[] = app()->environment();
        
        // Add level tag
        if (isset($context['level'])) {
            $tags[] = strtolower($context['level']);
        }
        
        return array_unique($tags);
    }
}