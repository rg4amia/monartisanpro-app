<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiErrorFormattingTest extends TestCase
{
 /**
  * Test that 404 errors are properly formatted for API routes
  */
 public function test_404_error_is_properly_formatted(): void
 {
  $response = $this->getJson('/api/v1/nonexistent-endpoint');

  $response->assertStatus(404)
   ->assertJsonStructure([
    'error',
    'message',
    'status_code'
   ])
   ->assertJson([
    'error' => 'NOT_FOUND',
    'status_code' => 404
   ]);
 }

 /**
  * Test that method not allowed errors are properly formatted
  */
 public function test_method_not_allowed_error_is_properly_formatted(): void
 {
  $response = $this->putJson('/api/v1/docs/spec', []);

  $response->assertStatus(405)
   ->assertJsonStructure([
    'error',
    'message',
    'status_code'
   ])
   ->assertJson([
    'error' => 'METHOD_NOT_ALLOWED',
    'status_code' => 405
   ]);
 }

 /**
  * Test that API documentation endpoints work
  */
 public function test_api_documentation_endpoints_work(): void
 {
  // Test OpenAPI spec endpoint
  $response = $this->getJson('/api/v1/docs/spec');
  $response->assertStatus(200)
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
}
