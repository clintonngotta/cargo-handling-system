<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
    }

    public function test_users_can_open_the_dashboard_outside_the_local_environment(): void
    {
        // Filament denies panel access in production unless the user model grants it.
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->actingAs(User::factory()->create())->get('/admin')->assertOk();
    }
}
