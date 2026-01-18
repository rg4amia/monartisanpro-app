assertIsString($data['error']);
            $this->assertIsString($data['message']);
            $this->assertIsInt($data['status_code']);
            $this->assertEquals($testCase['expectedStatus'], $data['status_code']);
        }
    }
}> '/api/v1/docs/spec', 'expectedStatus' => 405],
        ];

        foreach ($testCases as $testCase) {
            $response = $this->{$testCase['method'] . 'Json'}($testCase['url']);

            $response->assertStatus($testCase['expectedStatus'])
                     ->assertJsonStructure([
                         'error',
                         'message',
                         'status_code'
                     ]);

            $data = $response->json();
            $this->consistent structure
     */
    public function test_error_responses_have_consistent_structure(): void
    {
        // Test multiple error scenarios to ensure consistent structure
        $testCases = [
            ['method' => 'get', 'url' => '/api/v1/nonexistent-1', 'expectedStatus' => 404],
            ['method' => 'get', 'url' => '/api/v1/nonexistent-2', 'expectedStatus' => 404],
            ['method' => 'put', 'url' => '/api/v1/docs/spec', 'expectedStatus' => 405],
            ['method' => 'delete', 'url' =(200)
                 ->assertJsonStructure([
                     'openapi',
                     'info',
                     'paths'
                 ]);

        // Test Swagger UI endpoint
        $response = $this->get('/api/v1/docs/');
        $response->assertStatus(200)
                 ->assertSee('ProSartisan API Documentation');
    }

    /**
     * Test that error responses have
                     'message',
                     'status_code'
                 ])
                 ->assertJson([
                     'error' => 'METHOD_NOT_ALLOWED',
                     'status_code' => 405
                 ]);
    }

    /**
     * Test that the API documentation endpoints work
     */
    public function test_api_documentation_endpoints_work(): void
    {
        // Test OpenAPI spec endpoint
        $response = $this->getJson('/api/v1/docs/spec');
        $response->assertStatus                   'error' => 'NOT_FOUND',
                     'status_code' => 404
                 ]);
    }

    /**
     * Test that method not allowed errors are properly formatted
     */
    public function test_method_not_allowed_errors_are_properly_formatted(): void
    {
        // Try to use wrong HTTP method on the docs spec endpoint
        $response = $this->putJson('/api/v1/docs/spec', []);

        $response->assertStatus(405)
                 ->assertJsonStructure([
                     'error', extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that 404 errors are properly formatted for API routes
     */
    public function test_404_error_is_properly_formatted_for_api_routes(): void
    {
        $response = $this->getJson('/api/v1/nonexistent-endpoint');

        $response->assertStatus(404)
                 ->assertJsonStructure([
                     'error',
                     'message',
                     'status_code'
                 ])
                 ->assertJson([
  <?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

c
