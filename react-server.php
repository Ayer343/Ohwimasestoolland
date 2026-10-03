<?php
// react-server.php - High-performance server for Flutter app on Windows

require __DIR__ . '/vendor/autoload.php';

use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;

$loop = Loop::get();

// Configuration
$host = '0.0.0.0';
$port = 8080;

echo "\n========================================\n";
echo "🚀 ReactPHP Server Starting...\n";
echo "========================================\n";
echo "📡 Host: http://{$host}:{$port}\n";
echo "📱 For Flutter app: http://192.168.100.168:8080\n";
echo "⚡ Press Ctrl+C to stop\n";
echo "========================================\n\n";

// Create the HTTP server
$server = new HttpServer(function (ServerRequestInterface $request) {
    $startTime = microtime(true);
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();
    
    echo "[{$method}] {$path} - Processing...\n";
    
    // Handle health check
    if ($path === '/health' || $path === '/api/v1/health') {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        echo "[{$method}] {$path} - Completed in {$duration}ms\n";
        
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'success' => true,
                'status' => 'healthy',
                'timestamp' => date('c'),
                'server' => 'ReactPHP',
                'version' => '1.0.0'
            ])
        );
    }
    
    // Forward to Laravel
    return handleLaravelRequest($request, $startTime);
});

// Handle Laravel requests
function handleLaravelRequest(ServerRequestInterface $request, $startTime): Response
{
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();
    
    // Prepare server variables for Laravel
    $host = $request->getHeaderLine('Host');
    $query = $request->getUri()->getQuery();
    
    $_SERVER = [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path . ($query ? '?' . $query : ''),
        'QUERY_STRING' => $query,
        'SERVER_NAME' => explode(':', $host)[0],
        'SERVER_PORT' => 8080,
        'HTTP_HOST' => $host,
        'HTTP_USER_AGENT' => $request->getHeaderLine('User-Agent'),
        'HTTP_ACCEPT' => $request->getHeaderLine('Accept'),
        'HTTP_CONTENT_TYPE' => $request->getHeaderLine('Content-Type'),
        'REMOTE_ADDR' => $request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1',
        'SCRIPT_FILENAME' => __DIR__ . '/public/index.php',
        'SCRIPT_NAME' => '/index.php',
    ];
    
    // Add authorization header
    if ($request->hasHeader('Authorization')) {
        $_SERVER['HTTP_AUTHORIZATION'] = $request->getHeaderLine('Authorization');
    }
    
    // Add device headers for Flutter
    if ($request->hasHeader('X-Device-ID')) {
        $_SERVER['HTTP_X_DEVICE_ID'] = $request->getHeaderLine('X-Device-ID');
    }
    if ($request->hasHeader('X-Platform')) {
        $_SERVER['HTTP_X_PLATFORM'] = $request->getHeaderLine('X-Platform');
    }
    
    // Set request body
    $body = (string)$request->getBody();
    if (!empty($body)) {
        $GLOBALS['HTTP_RAW_POST_DATA'] = $body;
        $_POST = json_decode($body, true) ?? [];
    }
    
    // Capture Laravel's output
    ob_start();
    
    try {
        require __DIR__ . '/public/index.php';
        $content = ob_get_clean();
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $statusCode = http_response_code();
        
        echo "[{$method}] {$path} - Status: {$statusCode} - Duration: {$duration}ms\n";
        
        return new Response(
            $statusCode,
            ['Content-Type' => 'application/json'],
            $content
        );
    } catch (\Exception $e) {
        ob_end_clean();
        echo "❌ Error processing {$path}: " . $e->getMessage() . "\n";
        
        return new Response(
            500,
            ['Content-Type' => 'application/json'],
            json_encode([
                'success' => false,
                'error' => 'Server Error',
                'message' => $e->getMessage()
            ])
        );
    }
}

// Bind to all network interfaces
$socket = new SocketServer("{$host}:{$port}", [], $loop);
$server->listen($socket);

// Handle Windows shutdown (Ctrl+C)
if (PHP_OS_FAMILY === 'Windows') {
    echo "💡 Press Ctrl+C to stop the server\n\n";
    $loop->addSignal(SIGINT, function () use ($loop) {
        echo "\n\n🛑 Shutting down server...\n";
        $loop->stop();
    });
}

$loop->run();