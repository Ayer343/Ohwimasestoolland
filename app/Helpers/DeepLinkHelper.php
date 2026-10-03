<?php

namespace App\Helpers;

class DeepLinkHelper
{
    /**
     * Generate a deep link URL for the mobile app
     */
    public static function generateDeepLink(string $path, array $params = []): string
    {
        $scheme = config('app.deep_link_scheme', 'myapp');
        $host = config('app.deep_link_host', '');
        
        $query = http_build_query($params);
        
        if ($host) {
            $url = $scheme . '://' . $host . '/' . $path;
        } else {
            $url = $scheme . '://' . $path;
        }
        
        if ($query) {
            $url .= '?' . $query;
        }
        
        return $url;
    }
}