<?php

namespace App\Traits;

use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

trait HasCacheHeaders
{
    /**
     * Apply cache headers to a response.
     *
     * @param  \Illuminate\Http\Response|\Illuminate\Http\JsonResponse  $response
     * @param  int  $maxAge
     * @param  bool  $public
     * @param  bool  $etag
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    protected function withCacheHeaders($response, $maxAge = 3600, $public = true, $etag = false)
    {
        $cacheControl = ($public ? 'public' : 'private') . ', max-age=' . $maxAge;
        $response->headers->set('Cache-Control', $cacheControl);
        $response->headers->set('Pragma', $public ? 'cache' : 'no-cache');
        $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        
        if ($etag) {
            $content = $response->getContent();
            if ($content !== null) {
                $etagValue = md5($content);
                $response->headers->set('ETag', '"' . $etagValue . '"');
            }
        }
        
        return $response;
    }
}