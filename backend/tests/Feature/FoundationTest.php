<?php

namespace Tests\Feature;

use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_api_health_contract(): void
    {
        $this->getJson('/api/health')->assertOk()->assertExactJson(['data' => ['status' => 'ok']]);
    }

    public function test_api_requires_a_session_and_never_accepts_bearer_tokens(): void
    {
        $this->getJson('/api/groups')->assertUnauthorized()->assertJsonStructure(['message']);
        $this->withHeader('Authorization', 'Bearer 1|not-a-session')->getJson('/api/groups')->assertUnauthorized();
    }
}
