<?php

namespace Tests\Feature;

use App\Models\SearchSynonym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_cannot_manage_synonyms(): void
    {
        $this->getJson('/api/admin/search/synonyms')->assertStatus(401);
        $this->postJson('/api/admin/search/synonyms', [
            'term' => 'pubg',
            'synonyms' => ['پابجی'],
        ])->assertStatus(401);
    }

    public function test_admin_can_create_and_delete_synonym(): void
    {
        $login = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $login->assertOk();
        $token = (string) $login->json('data.access_token');

        $create = $this->withToken($token)->postJson('/api/admin/search/synonyms', [
            'term' => 'پابجي',
            'synonyms' => ['پابجی', 'pubg'],
        ]);
        $create->assertOk();
        $this->assertSame('پابجی', $create->json('data.term'));
        $id = (int) $create->json('data.id');
        $this->assertTrue(SearchSynonym::query()->whereKey($id)->exists());

        $this->withToken($token)->deleteJson('/api/admin/search/synonyms/'.$id)->assertOk();
        $this->assertFalse(SearchSynonym::query()->whereKey($id)->exists());
    }

    public function test_health_does_not_leak_meili_keys(): void
    {
        $login = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $token = (string) $login->json('data.access_token');
        $res = $this->withToken($token)->getJson('/api/admin/search/health');
        $res->assertOk();
        $body = json_encode($res->json());
        $this->assertStringNotContainsString('MEILISEARCH', (string) $body);
        $this->assertStringNotContainsString('7700', (string) $body);
    }
}
