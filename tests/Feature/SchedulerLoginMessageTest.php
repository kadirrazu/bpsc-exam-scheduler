<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerLoginMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_credentials_are_displayed_once_in_each_language(): void
    {
        $this->withoutVite();

        foreach (['bn', 'en'] as $locale) {
            $this->withSession(['locale' => $locale]);
            $this->from('/login')->post('/login', [
                'email' => 'missing@example.test',
                'password' => 'WrongPassword!9',
            ])->assertRedirect('/login')->assertSessionHasErrors('email');
            $errors = session('errors');
            $response = $this->view('auth.login', ['errors' => $errors]);
            $message = $locale === 'bn' ? 'Login failed' : 'These credentials do not match our records.';
            $this->assertSame(1, substr_count((string) $response, $message));
            $response->assertSee('is-invalid');
            $this->assertGuest();
        }
    }

    public function test_required_field_errors_remain_visible_once(): void
    {
        $this->withoutVite();
        $this->withSession(['locale' => 'en']);
        $this->from('/login')->post('/login', [])
            ->assertRedirect('/login')->assertSessionHasErrors(['email', 'password']);

        $errors = session('errors');
        $response = $this->view('auth.login', ['errors' => $errors]);
        foreach (['The email field is required.', 'The password field is required.'] as $message) {
            $this->assertSame(1, substr_count((string) $response, $message));
        }
        $this->assertGuest();
    }
}
