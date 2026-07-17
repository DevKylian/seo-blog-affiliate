<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_is_redirected_to_admin_instead_of_missing_home_route(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/login')->assertRedirect('/admin');
        $this->actingAs($admin)->get('/home')->assertRedirect('/admin');
    }

    public function test_guest_admin_access_still_goes_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }
}
