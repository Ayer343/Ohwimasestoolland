<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiLog>
 */
class ApiLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $endpoints = [
            '/api/v1/users',
            '/api/v1/products',
            '/api/v1/orders',
            '/api/v1/auth/login',
            '/api/v1/auth/register',
            '/api/v1/profile',
            '/api/v1/cart',
            '/api/v1/checkout',
            '/api/v1/payments',
            '/api/v1/settings',
        ];
        
        $statusCodes = [
            200, 201, 204, // Success
            400, 401, 403, 404, 422, // Client errors
            500, 502, 503, // Server errors
        ];
        
        $method = fake()->randomElement($methods);
        $statusCode = fake()->randomElement($statusCodes);
        
        // Generate appropriate request/response data based on method and status
        [$requestData, $responseData] = $this->generateData($method, $statusCode);
        
        return [
            'method' => $method,
            'endpoint' => fake()->randomElement($endpoints) . $this->addPathParameters(),
            'status_code' => $statusCode,
            'response_time' => fake()->randomFloat(2, 50, 5000),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'user_id' => fake()->boolean(70) ? User::factory() : null, // 70% have a user
            'request_data' => $requestData,
            'response_data' => $responseData,
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'updated_at' => function (array $attributes) {
                return fake()->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];
    }

    /**
     * Generate realistic request and response data based on method and status.
     */
    private function generateData(string $method, int $statusCode): array
    {
        $isSuccess = $statusCode >= 200 && $statusCode < 300;
        
        $requestData = match($method) {
            'GET' => [
                'page' => fake()->numberBetween(1, 5),
                'per_page' => fake()->randomElement([10, 25, 50]),
                'sort_by' => fake()->randomElement(['created_at', 'name', 'price']),
                'sort_order' => fake()->randomElement(['asc', 'desc']),
                'search' => fake()->boolean(30) ? fake()->word() : null,
            ],
            'POST' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'password' => '***FILTERED***',
                'title' => fake()->sentence(3),
                'description' => fake()->paragraph(),
                'price' => fake()->randomFloat(2, 10, 1000),
                'quantity' => fake()->numberBetween(1, 100),
            ],
            'PUT', 'PATCH' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'status' => fake()->randomElement(['active', 'inactive', 'pending']),
                'metadata' => [
                    'updated_by' => fake()->email(),
                    'update_reason' => fake()->sentence(),
                ],
            ],
            'DELETE' => [
                'confirm' => true,
                'reason' => fake()->optional()->sentence(),
            ],
            default => [],
        };

        $responseData = $isSuccess 
            ? $this->generateSuccessResponse($method)
            : $this->generateErrorResponse($statusCode);

        return [$requestData, $responseData];
    }

    /**
     * Generate success response data.
     */
    private function generateSuccessResponse(string $method): array
    {
        return match($method) {
            'GET' => [
                'data' => $this->generatePaginatedData(),
                'meta' => [
                    'current_page' => 1,
                    'from' => 1,
                    'last_page' => fake()->numberBetween(1, 10),
                    'per_page' => 15,
                    'to' => 15,
                    'total' => fake()->numberBetween(15, 150),
                ],
                'links' => [
                    'first' => 'http://api.example.com/resource?page=1',
                    'last' => 'http://api.example.com/resource?page=3',
                    'prev' => null,
                    'next' => 'http://api.example.com/resource?page=2',
                ],
            ],
            'POST' => [
                'message' => 'Resource created successfully',
                'data' => [
                    'id' => fake()->numberBetween(1000, 9999),
                    'name' => fake()->name(),
                    'email' => fake()->safeEmail(),
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString(),
                ],
            ],
            'PUT', 'PATCH' => [
                'message' => 'Resource updated successfully',
                'data' => [
                    'id' => fake()->numberBetween(1000, 9999),
                    'name' => fake()->name(),
                    'updated_at' => now()->toISOString(),
                ],
            ],
            'DELETE' => [
                'message' => 'Resource deleted successfully',
                'data' => null,
            ],
            default => [
                'message' => 'Success',
                'data' => [],
            ],
        };
    }

    /**
     * Generate error response data.
     */
    private function generateErrorResponse(int $statusCode): array
    {
        $errors = [
            'field' => [
                fake()->randomElement(['email', 'password', 'name', 'price']),
                fake()->randomElement(['The field is required.', 'Invalid format.', 'Already exists.']),
            ],
        ];

        return match($statusCode) {
            400 => [
                'message' => 'Bad Request',
                'errors' => $errors,
            ],
            401 => [
                'message' => 'Unauthorized',
                'error' => 'Authentication required',
            ],
            403 => [
                'message' => 'Forbidden',
                'error' => 'You do not have permission to access this resource',
            ],
            404 => [
                'message' => 'Not Found',
                'error' => 'The requested resource could not be found',
            ],
            422 => [
                'message' => 'Unprocessable Entity',
                'errors' => $errors,
            ],
            500 => [
                'message' => 'Internal Server Error',
                'error' => 'An unexpected error occurred',
                'trace_id' => 'err_' . strtoupper(fake()->bothify('??##??##')),
            ],
            default => [
                'message' => 'Error',
                'code' => $statusCode,
            ],
        };
    }

    /**
     * Generate paginated data for GET requests.
     */
    private function generatePaginatedData(): array
    {
        $items = [];
        $count = fake()->numberBetween(5, 15);
        
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'id' => fake()->numberBetween(1, 100),
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'status' => fake()->randomElement(['active', 'inactive', 'pending']),
                'created_at' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s'),
                'updated_at' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d H:i:s'),
            ];
        }
        
        return $items;
    }

    /**
     * Add path parameters to endpoints for realism.
     */
    private function addPathParameters(): string
    {
        $formats = [
            '', // No parameters
            '/' . fake()->numberBetween(1, 100),
            '/' . fake()->numberBetween(1, 100) . '/edit',
            '/' . fake()->uuid(),
            '?id=' . fake()->numberBetween(1, 100),
            '?userId=' . fake()->numberBetween(1, 50) . '&status=' . fake()->randomElement(['active', 'pending']),
        ];
        
        return fake()->randomElement($formats);
    }

    /**
     * State for successful API logs (2xx status codes).
     */
    public function successful(): static
    {
        return $this->state(fn(array $attributes) => [
            'status_code' => fake()->randomElement([200, 201, 204]),
            'response_data' => $this->generateSuccessResponse($attributes['method']),
        ]);
    }

    /**
     * State for client error API logs (4xx status codes).
     */
    public function clientError(): static
    {
        return $this->state(fn(array $attributes) => [
            'status_code' => fake()->randomElement([400, 401, 403, 404, 422]),
            'response_data' => $this->generateErrorResponse($attributes['status_code']),
        ]);
    }

    /**
     * State for server error API logs (5xx status codes).
     */
    public function serverError(): static
    {
        return $this->state(fn(array $attributes) => [
            'status_code' => fake()->randomElement([500, 502, 503]),
            'response_data' => $this->generateErrorResponse($attributes['status_code']),
        ]);
    }

    /**
     * State for specific HTTP method.
     */
    public function method(string $method): static
    {
        return $this->state(fn(array $attributes) => [
            'method' => strtoupper($method),
            'request_data' => $this->generateData($method, $attributes['status_code'])[0],
        ]);
    }

    /**
     * State for authenticated user requests.
     */
    public function authenticated(): static
    {
        return $this->state(fn() => [
            'user_id' => User::factory(),
        ]);
    }

    /**
     * State for guest requests (no user).
     */
    public function guest(): static
    {
        return $this->state(fn() => [
            'user_id' => null,
        ]);
    }

    /**
     * State for slow responses (> 1 second).
     */
    public function slow(): static
    {
        return $this->state(fn() => [
            'response_time' => fake()->randomFloat(2, 1000, 10000),
        ]);
    }

    /**
     * State for fast responses (< 100ms).
     */
    public function fast(): static
    {
        return $this->state(fn() => [
            'response_time' => fake()->randomFloat(2, 10, 100),
        ]);
    }

    /**
     * State for recent logs (last 24 hours).
     */
    public function recent(): static
    {
        return $this->state(fn() => [
            'created_at' => fake()->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * State for specific endpoint.
     */
    public function endpoint(string $endpoint): static
    {
        return $this->state(fn() => [
            'endpoint' => $endpoint,
        ]);
    }
}