<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EmailConfigurationService;

class EmailConfigurationController extends Controller
{
    protected $emailConfigService;

    public function __construct(EmailConfigurationService $emailConfigService)
    {
        $this->emailConfigService = $emailConfigService;
    }

    public function index()
    {
        $emailStatus = $this->emailConfigService->getConfigurationStatus();
        return view('admin.system-settings.email-index', compact('emailStatus'));
    }

    public function updateSmtp(Request $request)
    {
        $result = $this->emailConfigService->updateSmtpConfiguration($request->all());
        return response()->json($result);
    }

    public function testConnection(Request $request)
    {
        $result = $this->emailConfigService->testSmtpConnection($request->test_email);
        return response()->json($result);
    }

    public function updateEnvConfiguration(Request $request)
    {
        $result = $this->emailConfigService->updateEnvironmentConfiguration($request->all());
        return response()->json($result);
    }
}