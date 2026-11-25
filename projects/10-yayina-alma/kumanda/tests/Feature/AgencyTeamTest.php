<?php

namespace Tests\Feature;

use App\Enums\AgencyRole;
use App\Models\AgencyInvitation;
use App\Models\Site;
use App\Models\User;
use App\Notifications\AgencyInvitationSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgencyTeamTest extends TestCase
{
    use RefreshDatabase;

    private function member(User $owner): User
    {
        $member = User::factory()->create();
        $owner->currentAgency->addMember($member, AgencyRole::Member);
        $member->switchAgency($owner->currentAgency);

        return $member;
    }

    public function test_the_team_page_lists_members_and_roles(): void
    {
        $owner = $this->owner();
        $member = $this->member($owner);

        $this->actingAs($member)->get(route('agency.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('agency/edit')
            ->has('members', 2)
            ->where('canManage', false));
    }

    public function test_an_owner_invites_by_email(): void
    {
        Notification::fake();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('agency.invitations.store'), ['email' => 'Deniz@Ornek.com', 'role' => 'member'])->assertSessionHasNoErrors();

        $invitation = AgencyInvitation::sole();
        $this->assertSame('deniz@ornek.com', $invitation->email);
        Notification::assertSentOnDemand(AgencyInvitationSent::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'deniz@ornek.com');
    }

    public function test_inviting_again_renews_the_same_invitation(): void
    {
        Notification::fake();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('agency.invitations.store'), ['email' => 'deniz@ornek.com', 'role' => 'member']);
        $this->travel(3)->days();
        $this->actingAs($owner)->post(route('agency.invitations.store'), ['email' => 'deniz@ornek.com', 'role' => 'owner']);

        $this->assertSame(1, AgencyInvitation::count());
        $this->assertSame(AgencyRole::Owner, AgencyInvitation::sole()->role);
    }

    public function test_members_cannot_manage_the_agency(): void
    {
        $member = $this->member($this->owner());
        $site = Site::factory()->forAgency($member->currentAgency)->create();

        $this->actingAs($member)->post(route('agency.invitations.store'), ['email' => 'x@ornek.com', 'role' => 'owner'])->assertForbidden();
        $this->actingAs($member)->patch(route('agency.update'), ['name' => 'Ele geçirildi'])->assertForbidden();
        $this->actingAs($member)->delete(route('sites.destroy', $site))->assertForbidden();
        $this->assertModelExists($site);
    }

    public function test_accepting_an_invitation_joins_the_agency(): void
    {
        $owner = $this->owner('Mavi Pixel');
        $deniz = User::factory()->withAgency('Deniz Serbest')->create(['email' => 'deniz@ornek.com']);
        $invitation = $owner->currentAgency->invitations()->create(['email' => 'deniz@ornek.com', 'role' => 'member', 'expires_at' => now()->addDays(7)]);

        $this->actingAs($deniz)->get(URL::temporarySignedRoute('invitations.show', now()->addDay(), $invitation))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('emailMatches', true)->where('pending', true));

        $this->actingAs($deniz)->post(URL::temporarySignedRoute('invitations.accept', now()->addDay(), $invitation))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(AgencyRole::Member, $deniz->roleIn($owner->currentAgency));
        $this->assertSame('Mavi Pixel', $deniz->fresh()->currentAgency->name);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_an_invitation_link_only_works_for_its_email_and_only_once(): void
    {
        $owner = $this->owner();
        $invitation = $owner->currentAgency->invitations()->create(['email' => 'deniz@ornek.com', 'role' => 'owner', 'expires_at' => now()->addDays(7)]);
        $url = URL::temporarySignedRoute('invitations.accept', now()->addDay(), $invitation);

        // Bağlantı başkasına iletilmiş
        $this->actingAs($this->owner('Başka'))->post($url)->assertForbidden();

        $deniz = User::factory()->create(['email' => 'deniz@ornek.com']);
        $this->actingAs($deniz)->post($url)->assertRedirect();
        $this->actingAs($deniz)->post($url)->assertStatus(410);
    }

    public function test_expired_or_unsigned_invitation_links_are_rejected(): void
    {
        $owner = $this->owner();
        $deniz = User::factory()->create(['email' => 'deniz@ornek.com']);
        $invitation = $owner->currentAgency->invitations()->create(['email' => 'deniz@ornek.com', 'role' => 'member', 'expires_at' => now()->addDays(7)]);

        $this->actingAs($deniz)->post(route('invitations.accept', $invitation))->assertForbidden(); // imzasız

        $invitation->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->actingAs($deniz)->post(URL::temporarySignedRoute('invitations.accept', now()->addDay(), $invitation))->assertStatus(410);
    }

    public function test_an_owner_cannot_revoke_another_agencys_invitation(): void
    {
        $theirs = $this->owner('Kuzey')->currentAgency->invitations()->create(['email' => 'a@ornek.com', 'role' => 'member', 'expires_at' => now()->addDay()]);

        $this->actingAs($this->owner())->delete(route('agency.invitations.destroy', $theirs))->assertNotFound();
        $this->assertModelExists($theirs);
    }

    public function test_removing_members(): void
    {
        $owner = $this->owner();
        $member = $this->member($owner);
        $stranger = $this->owner('Kuzey');

        $this->actingAs($owner)->delete(route('agency.members.destroy', $stranger))->assertNotFound();
        $this->actingAs($owner)->delete(route('agency.members.destroy', $owner))->assertSessionHasErrors('member');

        $this->actingAs($owner)->delete(route('agency.members.destroy', $member))->assertSessionHasNoErrors();
        $this->assertNull($member->roleIn($owner->currentAgency));
    }

    public function test_the_last_owner_cannot_be_removed(): void
    {
        $owner = $this->owner();
        $second = User::factory()->create();
        $owner->currentAgency->addMember($second, AgencyRole::Owner);
        $second->switchAgency($owner->currentAgency);

        // İki sahip varken biri ötekini çıkarabilir
        $this->actingAs($second)->delete(route('agency.members.destroy', $owner))->assertSessionHasNoErrors();

        // Artık tek sahip $second; kendini çıkaramaz, başka sahip de yok
        $this->actingAs($second)->delete(route('agency.members.destroy', $second))->assertSessionHasErrors('member');
    }
}
