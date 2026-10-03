<?php
// app/Services/GoogleMapsService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class GoogleMapsService
{
    protected $apiKey;
    protected $baseUrl;
    protected $geocodingUrl;
    protected $directionsUrl;
    protected $distanceMatrixUrl;
    protected $placesUrl;
    protected $elevationUrl;
    protected $timezoneUrl;
    protected $roadsUrl;

    public function __construct()
    {
        // ✅ FIX: prefer the new `google_maps` config namespace, fall back to
        //    the legacy `google` namespace, then to a raw env var. This makes
        //    the service work whether keys are set via config/services.php
        //    (recommended) or left in the old location.
        $this->apiKey = config('services.google_maps.server_key')
            ?: config('services.google_maps.key')
            ?: config('services.google.maps_api_key')
            ?: env('GOOGLE_MAPS_API_KEY');

        $this->baseUrl          = 'https://maps.googleapis.com/maps/api';
        $this->geocodingUrl     = $this->baseUrl . '/geocode/json';
        $this->directionsUrl    = $this->baseUrl . '/directions/json';
        $this->distanceMatrixUrl = $this->baseUrl . '/distancematrix/json';
        $this->placesUrl        = $this->baseUrl . '/place';
        $this->elevationUrl     = $this->baseUrl . '/elevation/json';
        $this->timezoneUrl      = $this->baseUrl . '/timezone/json';
        $this->roadsUrl         = $this->baseUrl . '/roads';
    }

    /**
     * ✅ NEW: Geocode a Ghana Post GPS digital address (or any free-form
     * address) and return coordinates in the shape the property controllers
     * expect:
     *
     *   ['lat' => float, 'lng' => float, 'city' => ?string, 'source' => string]
     *
     * Returns null on any failure so callers can degrade gracefully.
     *
     * This is a thin wrapper around `geocodeAddress()` — the underlying
     * geocoder logic stays in one place.
     */
    public function geocodeDigitalAddress(string $digitalAddress): ?array
    {
        $digitalAddress = trim($digitalAddress);

        if ($digitalAddress === '') {
            return null;
        }

        try {
            $result = $this->geocodeAddress($digitalAddress, [
                'region'   => config('services.google_maps.maps.default_region', 'GH'),
                'language' => 'en',
            ]);

            // `geocodeAddress()` returns:
            //   ['success' => true, 'latitude' => float, 'longitude' => float,
            //    'formatted_address' => string, 'place_id' => string,
            //    'address_components' => array, 'full_response' => array]
            // or
            //   ['success' => false, 'error' => string, 'status' => string]
            if (empty($result['success'])) {
                Log::info('geocodeDigitalAddress: no results', [
                    'digital_address' => $digitalAddress,
                    'status'          => $result['status'] ?? 'unknown',
                    'error'           => $result['error']  ?? null,
                ]);
                return null;
            }

            if (!isset($result['latitude'], $result['longitude'])) {
                return null;
            }

            // Best-effort city extraction from the address components.
            $city = null;
            foreach (($result['address_components'] ?? []) as $component) {
                $types = $component['types'] ?? [];
                if (array_intersect($types, [
                    'locality',
                    'postal_town',
                    'administrative_area_level_2',
                ])) {
                    $city = $component['long_name'] ?? null;
                    if ($city) {
                        break;
                    }
                }
            }

            return [
                'lat'    => (float) $result['latitude'],
                'lng'    => (float) $result['longitude'],
                'city'   => $city,
                'source' => 'google',
            ];

        } catch (\Throwable $e) {
            Log::warning('geocodeDigitalAddress: exception', [
                'digital_address' => $digitalAddress,
                'error'           => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Geocode an address to coordinates.
     */
    public function geocodeAddress(string $address, array $params = []): array
    {
        $cacheKey = 'geocode_' . md5($address . json_encode($params));

        return Cache::remember($cacheKey, 86400, function () use ($address, $params) {
            try {
                $response = Http::get($this->geocodingUrl, array_merge([
                    'address' => $address,
                    'key'     => $this->apiKey,
                ], $params));

                $data = $response->json();

                if (($data['status'] ?? null) === 'OK' && !empty($data['results'])) {
                    $location = $data['results'][0]['geometry']['location'];

                    return [
                        'success'            => true,
                        'latitude'           => $location['lat'],
                        'longitude'          => $location['lng'],
                        'formatted_address'  => $data['results'][0]['formatted_address'],
                        'place_id'           => $data['results'][0]['place_id'],
                        'address_components' => $data['results'][0]['address_components'],
                        'full_response'      => $data,
                    ];
                }

                Log::warning('Geocoding failed', [
                    'address'       => $address,
                    'status'        => $data['status'] ?? 'UNKNOWN',
                    'error_message' => $data['error_message'] ?? null,
                ]);

                return [
                    'success' => false,
                    'error'   => $data['error_message'] ?? 'Geocoding failed',
                    'status'  => $data['status'] ?? 'UNKNOWN',
                ];

            } catch (\Exception $e) {
                Log::error('Geocoding exception: ' . $e->getMessage(), [
                    'address' => $address,
                    'trace'   => $e->getTraceAsString(),
                ]);

                return [
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        });
    }

    /**
     * Reverse geocode coordinates to address.
     */
    public function reverseGeocode(float $latitude, float $longitude, array $params = []): array
    {
        $cacheKey = 'reverse_geocode_' . md5($latitude . ',' . $longitude . json_encode($params));

        return Cache::remember($cacheKey, 86400, function () use ($latitude, $longitude, $params) {
            try {
                $response = Http::get($this->geocodingUrl, array_merge([
                    'latlng' => "{$latitude},{$longitude}",
                    'key'    => $this->apiKey,
                ], $params));

                $data = $response->json();

                if (($data['status'] ?? null) === 'OK' && !empty($data['results'])) {
                    return [
                        'success'            => true,
                        'formatted_address'  => $data['results'][0]['formatted_address'],
                        'place_id'           => $data['results'][0]['place_id'],
                        'address_components' => $data['results'][0]['address_components'],
                        'full_response'      => $data,
                    ];
                }

                Log::warning('Reverse geocoding failed', [
                    'latitude'  => $latitude,
                    'longitude' => $longitude,
                    'status'    => $data['status'] ?? 'UNKNOWN',
                ]);

                return [
                    'success' => false,
                    'error'   => $data['error_message'] ?? 'Reverse geocoding failed',
                    'status'  => $data['status'] ?? 'UNKNOWN',
                ];

            } catch (\Exception $e) {
                Log::error('Reverse geocoding exception: ' . $e->getMessage());
                return [
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        });
    }

    /**
     * Get directions between two points.
     */
    public function getDirections(
        $origin,
        $destination,
        array $waypoints = [],
        array $params = []
    ): array {
        try {
            $requestParams = [
                'origin'      => $this->formatLocation($origin),
                'destination' => $this->formatLocation($destination),
                'key'         => $this->apiKey,
            ];

            if (!empty($waypoints)) {
                $requestParams['waypoints'] = implode('|', array_map(function ($wp) {
                    return $this->formatLocation($wp);
                }, $waypoints));
            }

            $requestParams = array_merge($requestParams, $params);

            $response = Http::get($this->directionsUrl, $requestParams);
            $data     = $response->json();

            if (($data['status'] ?? null) === 'OK') {
                $route = $data['routes'][0];
                $leg   = $route['legs'][0];

                return [
                    'success' => true,
                    'distance' => [
                        'text'  => $leg['distance']['text'],
                        'value' => $leg['distance']['value'],
                    ],
                    'duration' => [
                        'text'  => $leg['duration']['text'],
                        'value' => $leg['duration']['value'],
                    ],
                    'start_address'   => $leg['start_address'],
                    'end_address'     => $leg['end_address'],
                    'polyline'        => $route['overview_polyline']['points'],
                    'steps'           => $leg['steps'],
                    'waypoint_order'  => $route['waypoint_order'] ?? [],
                    'full_response'   => $data,
                ];
            }

            Log::warning('Directions request failed', [
                'origin'      => $origin,
                'destination' => $destination,
                'status'      => $data['status'] ?? 'UNKNOWN',
            ]);

            return [
                'success' => false,
                'error'   => $data['error_message'] ?? 'Directions request failed',
                'status'  => $data['status'] ?? 'UNKNOWN',
            ];

        } catch (\Exception $e) {
            Log::error('Directions exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get distance matrix between multiple origins and destinations.
     */
    public function getDistanceMatrix(
        array $origins,
        array $destinations,
        array $params = []
    ): array {
        try {
            $requestParams = [
                'origins'      => implode('|', array_map([$this, 'formatLocation'], $origins)),
                'destinations' => implode('|', array_map([$this, 'formatLocation'], $destinations)),
                'key'          => $this->apiKey,
            ];

            $requestParams = array_merge($requestParams, $params);

            $response = Http::get($this->distanceMatrixUrl, $requestParams);
            $data     = $response->json();

            if (($data['status'] ?? null) === 'OK') {
                return [
                    'success'               => true,
                    'rows'                  => $data['rows'],
                    'origin_addresses'      => $data['origin_addresses'],
                    'destination_addresses' => $data['destination_addresses'],
                    'full_response'         => $data,
                ];
            }

            Log::warning('Distance matrix request failed', [
                'status' => $data['status'] ?? 'UNKNOWN',
            ]);

            return [
                'success' => false,
                'error'   => $data['error_message'] ?? 'Distance matrix request failed',
                'status'  => $data['status'] ?? 'UNKNOWN',
            ];

        } catch (\Exception $e) {
            Log::error('Distance matrix exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Search for places near a location.
     */
    public function searchNearby(
        string $type,
        float $latitude,
        float $longitude,
        int $radius = 1000,
        array $params = []
    ): array {
        try {
            $requestParams = [
                'location' => "{$latitude},{$longitude}",
                'radius'   => $radius,
                'type'     => $type,
                'key'      => $this->apiKey,
            ];

            $requestParams = array_merge($requestParams, $params);

            $response = Http::get($this->placesUrl . '/nearbysearch/json', $requestParams);
            $data     = $response->json();

            if (($data['status'] ?? null) === 'OK') {
                return [
                    'success'          => true,
                    'results'          => $data['results'],
                    'next_page_token'  => $data['next_page_token'] ?? null,
                    'full_response'    => $data,
                ];
            }

            return [
                'success' => false,
                'error'   => $data['error_message'] ?? 'Search failed',
                'status'  => $data['status'] ?? 'UNKNOWN',
            ];

        } catch (\Exception $e) {
            Log::error('Place search exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get place details by place ID.
     */
    public function getPlaceDetails(string $placeId, array $params = []): array
    {
        $cacheKey = 'place_details_' . $placeId . json_encode($params);

        return Cache::remember($cacheKey, 86400, function () use ($placeId, $params) {
            try {
                $requestParams = [
                    'place_id' => $placeId,
                    'key'      => $this->apiKey,
                ];

                $requestParams = array_merge($requestParams, $params);

                $response = Http::get($this->placesUrl . '/details/json', $requestParams);
                $data     = $response->json();

                if (($data['status'] ?? null) === 'OK') {
                    return [
                        'success'       => true,
                        'result'        => $data['result'],
                        'full_response' => $data,
                    ];
                }

                return [
                    'success' => false,
                    'error'   => $data['error_message'] ?? 'Place details request failed',
                    'status'  => $data['status'] ?? 'UNKNOWN',
                ];

            } catch (\Exception $e) {
                Log::error('Place details exception: ' . $e->getMessage());
                return [
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        });
    }

    /**
     * Autocomplete place search.
     */
    public function autocomplete(string $input, array $params = []): array
    {
        try {
            $requestParams = [
                'input' => $input,
                'key'   => $this->apiKey,
            ];

            $requestParams = array_merge($requestParams, $params);

            $response = Http::get($this->placesUrl . '/autocomplete/json', $requestParams);
            $data     = $response->json();

            if (($data['status'] ?? null) === 'OK') {
                return [
                    'success'       => true,
                    'predictions'   => $data['predictions'],
                    'full_response' => $data,
                ];
            }

            return [
                'success' => false,
                'error'   => $data['error_message'] ?? 'Autocomplete failed',
                'status'  => $data['status'] ?? 'UNKNOWN',
            ];

        } catch (\Exception $e) {
            Log::error('Autocomplete exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get elevation data for a location.
     */
    public function getElevation(array $locations, array $params = []): array
    {
        try {
            $locationsParam = is_array($locations[0])
                ? implode('|', array_map(function ($loc) {
                    return "{$loc['lat']},{$loc['lng']}";
                }, $locations))
                : "{$locations[0]},{$locations[1]}";

            $requestParams = [
                'locations' => $locationsParam,
                'key'       => $this->apiKey,
            ];

            $requestParams = array_merge($requestParams, $params);

            $response = Http::get($this->elevationUrl, $requestParams);
            $data     = $response->json();

            if (($data['status'] ?? null) === 'OK') {
                return [
                    'success'       => true,
                    'results'       => $data['results'],
                    'full_response' => $data,
                ];
            }

            return [
                'success' => false,
                'error'   => $data['error_message'] ?? 'Elevation request failed',
                'status'  => $data['status'] ?? 'UNKNOWN',
            ];

        } catch (\Exception $e) {
            Log::error('Elevation exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get timezone for a location.
     */
    public function getTimezone(float $latitude, float $longitude, ?int $timestamp = null): array
    {
        $timestamp = $timestamp ?? time();
        $cacheKey  = 'timezone_' . md5($latitude . ',' . $longitude . '_' . date('Y-m-d', $timestamp));

        return Cache::remember($cacheKey, 86400, function () use ($latitude, $longitude, $timestamp) {
            try {
                $response = Http::get($this->timezoneUrl, [
                    'location'  => "{$latitude},{$longitude}",
                    'timestamp' => $timestamp,
                    'key'       => $this->apiKey,
                ]);

                $data = $response->json();

                if (($data['status'] ?? null) === 'OK') {
                    return [
                        'success'       => true,
                        'timezone_id'   => $data['timeZoneId'],
                        'timezone_name' => $data['timeZoneName'],
                        'dst_offset'    => $data['dstOffset'],
                        'raw_offset'    => $data['rawOffset'],
                        'full_response' => $data,
                    ];
                }

                return [
                    'success' => false,
                    'error'   => $data['error_message'] ?? 'Timezone request failed',
                    'status'  => $data['status'] ?? 'UNKNOWN',
                ];

            } catch (\Exception $e) {
                Log::error('Timezone exception: ' . $e->getMessage());
                return [
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        });
    }

    /**
     * Snaps a point to the nearest road.
     */
    public function snapToRoads(array $points, bool $interpolate = false): array
    {
        try {
            $path = is_array($points[0])
                ? implode('|', array_map(function ($p) {
                    return "{$p['lat']},{$p['lng']}";
                }, $points))
                : implode('|', $points);

            $response = Http::get($this->roadsUrl . '/snapToRoads', [
                'path'        => $path,
                'interpolate' => $interpolate ? 'true' : 'false',
                'key'         => $this->apiKey,
            ]);

            $data = $response->json();

            if (isset($data['snappedPoints'])) {
                return [
                    'success'       => true,
                    'points'        => $data['snappedPoints'],
                    'full_response' => $data,
                ];
            }

            return [
                'success' => false,
                'error'   => $data['error_message'] ?? 'Snap to roads failed',
            ];

        } catch (\Exception $e) {
            Log::error('Snap to roads exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate distance between two coordinates using Haversine formula.
     */
    public function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        string $unit = 'km'
    ): float {
        $theta = $lon1 - $lon2;
        $dist  = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +
                 cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist  = acos($dist);
        $dist  = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        if ($unit === 'km') {
            return $miles * 1.609344;
        } elseif ($unit === 'meters') {
            return $miles * 1609.344;
        }

        return $miles;
    }

    /**
     * Format a location for Google Maps API.
     */
    protected function formatLocation($location): string
    {
        if (is_array($location) && isset($location['lat']) && isset($location['lng'])) {
            return "{$location['lat']},{$location['lng']}";
        }

        if (is_string($location)) {
            return $location;
        }

        return '';
    }

    /**
     * Validate an address using Google Maps API.
     */
    public function validateAddress(string $address): array
    {
        $result = $this->geocodeAddress($address);

        if (!$result['success']) {
            return [
                'valid'   => false,
                'message' => 'Invalid address',
                'error'   => $result['error'] ?? null,
            ];
        }

        return [
            'valid'              => true,
            'formatted_address'  => $result['formatted_address'],
            'latitude'           => $result['latitude'],
            'longitude'          => $result['longitude'],
            'place_id'           => $result['place_id'],
            'address_components' => $result['address_components'],
        ];
    }

    /**
     * Get static map image URL.
     */
    public function getStaticMapUrl(
        float $latitude,
        float $longitude,
        int $zoom = 15,
        array $markers = [],
        array $params = []
    ): string {
        $baseUrl = $this->baseUrl . '/staticmap';

        $queryParams = [
            'center' => "{$latitude},{$longitude}",
            'zoom'   => $zoom,
            'size'   => '600x400',
            'key'    => $this->apiKey,
        ];

        if (!empty($markers)) {
            $queryParams['markers'] = implode('|', array_map(function ($marker) {
                return "{$marker['lat']},{$marker['lng']}";
            }, $markers));
        }

        $queryParams = array_merge($queryParams, $params);

        return $baseUrl . '?' . http_build_query($queryParams);
    }

    /**
     * Get place photo URL.
     */
    public function getPlacePhotoUrl(string $photoReference, int $maxWidth = 400, int $maxHeight = 400): string
    {
        return $this->placesUrl . '/photo?' . http_build_query([
            'photoreference' => $photoReference,
            'maxwidth'       => $maxWidth,
            'maxheight'      => $maxHeight,
            'key'            => $this->apiKey,
        ]);
    }

    /**
     * Check if Google Maps API is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get API usage statistics.
     */
    public function getUsageStats(): array
    {
        return [
            'api_key'     => $this->apiKey ? 'Configured' : 'Not Configured',
            'daily_quota' => 'Unknown',
            'usage_today' => 'Unknown',
        ];
    }

    /**
     * Clear geocoding cache.
     *
     * For file/array cache stores we can't enumerate keys, so we clear the
     * exact key when an address is provided. When no address is given we
     * flush the entire cache — the caller must understand this side effect.
     */
    public function clearCache(?string $address = null): void
    {
        if ($address) {
            Cache::forget('geocode_' . md5($address . json_encode([])));
            Cache::forget('geocode_' . md5($address . json_encode([
                'region'   => config('services.google_maps.maps.default_region', 'GH'),
                'language' => 'en',
            ])));

            // Best-effort: also clear the reverse lookup if the input looks
            // like "lat,lng"
            if (preg_match('/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/', $address)) {
                Cache::forget('reverse_geocode_' . md5($address . json_encode([])));
            }
        } else {
            Cache::flush();
        }
    }
}