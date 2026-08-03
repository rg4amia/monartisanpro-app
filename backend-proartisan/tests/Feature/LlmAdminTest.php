<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StagingItem;
use App\Models\ProductionItem;
use App\Models\ImportHistory;
use App\Models\LlmAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LlmAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_get_staging_items(): void
    {
        StagingItem::create([
            'id' => 'stage-1',
            'raw_pdf_source' => 'test.pdf',
            'original_extracted_text' => 'Some extracted text',
            'generated_json' => ['title' => 'Test Item'],
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.api.llm.staging.index'));

        $response->assertOk()
            ->assertJsonFragment(['id' => 'stage-1']);
    }

    public function test_can_store_staging_item(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.api.llm.staging.store'), [
                'id' => 'stage-2',
                'raw_pdf_source' => 'test2.pdf',
                'original_extracted_text' => 'Text content',
                'generated_json' => ['title' => 'Another Test Item'],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('staging_items', ['id' => 'stage-2']);
    }

    public function test_can_approve_staging_item(): void
    {
        $staging = StagingItem::create([
            'id' => 'stage-3',
            'raw_pdf_source' => 'test3.pdf',
            'original_extracted_text' => 'Text content',
            'generated_json' => [
                'title' => 'Approved Item',
                'metadata' => ['tags_pathologies' => ['tag1', 'tag2']]
            ],
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.api.llm.staging.approve', ['id' => $staging->id]));

        $response->assertOk();
        $this->assertSame('APPROVED', $staging->fresh()->status);
        $this->assertDatabaseHas('production_items', [
            'id' => 'stage-3',
            'tags' => 'tag1,tag2'
        ]);
    }

    public function test_can_reject_staging_item(): void
    {
        $staging = StagingItem::create([
            'id' => 'stage-4',
            'raw_pdf_source' => 'test4.pdf',
            'original_extracted_text' => 'Text content',
            'generated_json' => ['title' => 'Rejected Item'],
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.api.llm.staging.reject', ['id' => $staging->id]), [
                'reviewer_notes' => 'Invalid content structure',
            ]);

        $response->assertOk();
        $this->assertSame('REJECTED', $staging->fresh()->status);
        $this->assertSame('Invalid content structure', $staging->fresh()->reviewer_notes);
    }

    public function test_can_search_production_items(): void
    {
        ProductionItem::create([
            'id' => 'prod-1',
            'generated_json' => ['title' => 'Matched Prod Item'],
            'tags' => 'fissure,toiture',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.api.llm.search'), [
                'tags' => ['fissure'],
            ]);

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Matched Prod Item']);
    }

    public function test_can_upload_attachment(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.api.llm.upload'), [
                'filename' => 'test_document.pdf',
                'content' => base64_encode('fake pdf content'),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['status', 'id', 'file_link']);
    }
}
