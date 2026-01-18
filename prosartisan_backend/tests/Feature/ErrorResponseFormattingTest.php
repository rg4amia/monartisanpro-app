<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ErrorResponseFormattingTest extends TestCase
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
    'error' => 'NOT_FOUND',
    'status_code' => 404
   ]);
 }

 /**
  * Test that validation errors are properly formatted
  */
 public function test_validation_errors_are_properly_formatted(): void
 {
  // Try to create a mission without required fields
  $response = $this->postJson('/api/v1/missions', []);

  $response->assertStatus(422)
   ->assertJsonStructure([
    'error',
    'message',
    'status_code',
    'validation_errors'
   ])
   ->assertJson([
    'error' => 'VALIDATION_ERROR',
    'status_code' => 422
   ]);
 }

 /**
  * Test that unauthorized errors are properly formatted
  */
 public function test_unauthorized_errors_are_properly_formatted(): void
 {
  // Try to access a protected endpoint without authentication
  $response = $this->getJson('/api/v1/missions');

  $response->assertStatus(401)
   ->assertJsonStructure([
    'error',
    'message',
    'status_code'
   ])
   ->assertJson([
    'error' => 'UNAUTHORIZED',
    'status_code' => 401
   ]);
 }

 /**
  * Test that method not allowed errors are properly formatted
  */
 public function test_method_not_allowed_errors_are_properly_formatted(): void
 {
  // Try to use wrong HTTP method
  $response = $this->putJson('/api/v1/auth/login', []);

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
  * Test that the API documentation endpoints work
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
