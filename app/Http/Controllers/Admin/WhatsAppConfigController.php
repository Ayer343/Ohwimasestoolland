<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class WhatsAppConfigController extends Controller
{
    /**
     * Redirect to the correct WhatsApp provider controller
     */
    public function index()
    {
        return redirect()->route('admin.whatsapp-providers.index');
    }

    /**
     * Handle any other methods by redirecting
     */
    public function __call($method, $arguments)
    {
        return redirect()->route('admin.whatsapp-providers.index')
            ->with('info', 'Feature moved to WhatsApp Provider settings');
    }
}