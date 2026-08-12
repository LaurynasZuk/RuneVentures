<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MainTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('main'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_main()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('main'));
        $response->assertOk();
    }
}
