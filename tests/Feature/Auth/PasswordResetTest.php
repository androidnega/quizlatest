<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_routes_are_not_available(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'x@example.com'])->assertNotFound();
        $this->get('/reset-password/token')->assertNotFound();
        $this->post('/reset-password', [])->assertNotFound();
    }
}
