<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\JsonResponse;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * ✅ Clean BOM from JSON response
     */
    protected function cleanJsonResponse($data, $status = 200, array $headers = [], $options = 0): JsonResponse
    {
        $response = response()->json($data, $status, $headers, $options);
        
        // Get and clean content
        $content = $response->getContent();
        
        if ($content !== null && $content !== '') {
            // Remove double BOM (EF BB BF EF BB BF)
            if (substr($content, 0, 6) === "\xEF\xBB\xBF\xEF\xBB\xBF") {
                $content = substr($content, 6);
                \Log::debug('🧹 Removed double BOM from response');
            }
            // Remove single BOM (EF BB BF)
            elseif (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $content = substr($content, 3);
                \Log::debug('🧹 Removed single BOM from response');
            }
            // Remove Unicode BOM
            elseif (substr($content, 0, 1) === "\u{FEFF}") {
                $content = substr($content, 1);
                \Log::debug('🧹 Removed Unicode BOM from response');
            }
            
            $response->setContent($content);
            
            // Update Content-Length header
            $response->headers->set('Content-Length', strlen($content));
        }
        
        return $response;
    }

    /**
     * ✅ Clean existing response
     */
    protected function cleanResponse($response)
    {
        if (!$response instanceof JsonResponse) {
            return $response;
        }
        
        $content = $response->getContent();
        
        if ($content === null || $content === '') {
            return $response;
        }
        
        // Remove double BOM
        if (substr($content, 0, 6) === "\xEF\xBB\xBF\xEF\xBB\xBF") {
            $content = substr($content, 6);
        }
        // Remove single BOM
        elseif (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        // Remove Unicode BOM
        elseif (substr($content, 0, 1) === "\u{FEFF}") {
            $content = substr($content, 1);
        }
        
        $response->setContent($content);
        $response->headers->set('Content-Length', strlen($content));
        
        return $response;
    }
}