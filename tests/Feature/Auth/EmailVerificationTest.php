<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_verification_screen_redirects_to_catalog(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertRedirect(route('front.products.index'));
    }

    public function test_old_signed_links_redirect_without_marking_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake([Verified::class]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);
        $this->assertNull($user->fresh()->email_verified_at);
        $response->assertRedirect(route('front.products.index'));
    }

    public function test_resend_endpoint_does_not_send_verification_notifications(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'))
            ->assertRedirect(route('front.products.index'));
        Notification::assertNothingSent();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_profile_does_not_display_a_verification_requirement(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')->where('mustVerifyEmail', false));
    }

    public function test_unverified_staff_can_access_dashboard_and_reports(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole(Role::findOrCreate('accounting', 'web'));

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->get(route('admin.fine-reports.index'))->assertOk();
        $this->get(route('admin.users.index'))->assertForbidden();
    }
}
