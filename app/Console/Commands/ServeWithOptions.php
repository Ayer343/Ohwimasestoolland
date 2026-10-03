<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;
use Symfony\Component\Process\Process;

class ServeWithOptions extends ServeCommand
{
    protected $signature = 'serve:enhanced 
                            {--port=8000 : The port to serve the application on}
                            {--host=127.0.0.1 : The host address to bind to}
                            {--workers=4 : Number of worker processes (for ReactPHP)}';
    
    protected $description = 'Serve the application with enhanced settings for mobile API';

    public function handle()
    {
        $this->info('Starting enhanced Laravel development server for mobile API...');
        $this->info('With increased memory and timeout limits');
        
        $host = $this->input->getOption('host');
        $port = $this->input->getOption('port');
        
        // Use PHP's built-in server with custom php.ini settings
        $phpIni = $this->createTempPhpIni();
        
        $command = [
            'php',
            '-c', $phpIni,
            '-S', "{$host}:{$port}",
            '-t', base_path('public'),
            '-d', 'memory_limit=512M',
            '-d', 'max_execution_time=300',
            '-d', 'post_max_size=50M',
            '-d', 'upload_max_filesize=50M',
            '-d', 'max_input_vars=5000',
            base_path('server.php')
        ];
        
        $process = new Process($command);
        $process->setTimeout(null);
        $process->start();
        
        $this->info("Laravel development server started on http://{$host}:{$port}");
        $this->info("Press Ctrl+C to stop the server");
        
        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                $this->info($data);
            } else {
                $this->error($data);
            }
        }
    }
    
    private function createTempPhpIni(): string
    {
        $tempDir = storage_path('framework/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $iniPath = $tempDir . '/dev-php.ini';
        
        $iniContent = [
            'memory_limit = 512M',
            'max_execution_time = 300',
            'max_input_time = 300',
            'post_max_size = 50M',
            'upload_max_filesize = 50M',
            'max_input_vars = 5000',
            'default_socket_timeout = 300',
            'mysql.connect_timeout = 300',
            'session.gc_maxlifetime = 1440',
        ];
        
        file_put_contents($iniPath, implode("\n", $iniContent));
        
        return $iniPath;
    }
}