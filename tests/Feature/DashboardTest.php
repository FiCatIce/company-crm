<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_users_with_a_role_can_visit_the_dashboard()
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(userWithRole('admin'));

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_users_without_a_role_are_forbidden_from_the_dashboard()
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('dashboard'));
        $response->assertForbidden();
    }
}
