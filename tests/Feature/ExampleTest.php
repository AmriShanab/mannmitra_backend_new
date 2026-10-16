<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_health_check_responds(): void
    {
        $this->get('/up')->assertStatus(200);
    }

    public function test_the_root_url_redirects_to_the_staff_login(): void
    {
        $this->get('/')->assertRedirect('/admin/login');
    }
}
