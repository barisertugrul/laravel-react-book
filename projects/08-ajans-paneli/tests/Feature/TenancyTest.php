<?php

namespace Tests\Feature;

use App\Enums\AgencyRole;
use App\Models\Agency;
use App\Models\Site;
use App\Models\SiteCheck;
use App\Tenancy\CurrentAgency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_agency_sees_only_its_own_sites(): void
    {
        $ali = $this->owner('Mavi Pixel');
        $zeynep = $this->owner('Kuzey Web');
        Site::factory()->forAgency($ali->currentAgency)->create(['name' => 'Lale Kafe']);
        Site::factory()->forAgency($zeynep->currentAgency)->create(['name' => 'Atlas Hukuk']);

        $this->actingAs($ali)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('sites', 1)
            ->where('sites.0.name', 'Lale Kafe'));
    }

    public function test_another_agencys_site_is_not_found(): void
    {
        $ali = $this->owner('Mavi Pixel');
        $theirs = Site::factory()->forAgency($this->owner('Kuzey Web')->currentAgency)->create();

        $this->actingAs($ali)->get(route('sites.show', $theirs))->assertNotFound();
        $this->actingAs($ali)->post(route('sites.check', $theirs))->assertNotFound();
        $this->actingAs($ali)->delete(route('sites.destroy', $theirs))->assertNotFound();

        $this->assertModelExists($theirs);
    }

    public function test_new_records_get_the_current_agency_automatically(): void
    {
        $agency = Agency::factory()->create();
        $this->actAsAgency($agency);

        $site = Site::factory()->make(['agency_id' => null]);
        $site->save();

        $this->assertSame($agency->id, $site->agency_id);
    }

    public function test_creating_a_record_without_an_agency_context_fails_loudly(): void
    {
        $this->expectException(LogicException::class);

        Site::factory()->make(['agency_id' => null])->save();
    }

    public function test_without_a_context_queries_return_nothing_unless_explicitly_across_agencies(): void
    {
        Site::factory()->count(3)->create();

        $this->assertSame(0, Site::count());                  // kapalı başarısızlık
        $this->assertSame(3, Site::acrossAgencies()->count()); // bilinçli istisna
    }

    public function test_run_switches_context_and_restores_it(): void
    {
        [$a, $b] = Agency::factory()->count(2)->create();
        Site::factory()->forAgency($a)->create();
        Site::factory()->forAgency($b)->count(2)->create();
        $current = app(CurrentAgency::class);

        $current->set($a);
        $countInB = $current->run($b, fn () => Site::count());

        $this->assertSame(2, $countInB);
        $this->assertSame($a->id, $current->id());
        $this->assertSame(1, Site::count());
    }

    public function test_site_checks_are_scoped_too(): void
    {
        $site = Site::factory()->create();
        $this->actAsAgency($site->agency);
        $site->checks()->create(['status' => 'ok', 'issues' => [], 'checked_at' => now()]);

        $this->actAsAgency(Agency::factory()->create());

        $this->assertSame(0, SiteCheck::count());
    }

    public function test_switching_to_an_agency_you_belong_to(): void
    {
        $user = $this->owner('Mavi Pixel');
        $other = Agency::factory()->create(['name' => 'Kuzey Web']);
        $other->addMember($user, AgencyRole::Member);

        $this->actingAs($user)->post(route('agencies.select', $other))->assertRedirect(route('dashboard'));

        $this->assertSame($other->id, $user->fresh()->current_agency_id);
    }

    public function test_switching_to_a_foreign_agency_is_not_found(): void
    {
        $user = $this->owner();
        $foreign = Agency::factory()->create();

        $this->actingAs($user)->post(route('agencies.select', $foreign))->assertNotFound();
        $this->assertNotSame($foreign->id, $user->fresh()->current_agency_id);
    }

    public function test_a_removed_member_falls_back_to_another_agency(): void
    {
        $user = $this->owner('Kendi Ajansım');
        $other = Agency::factory()->create();
        $other->addMember($user, AgencyRole::Member);
        $user->switchAgency($other);

        $other->users()->detach($user->id); // ekipten çıkarıldı

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->assertSame('Kendi Ajansım', $user->fresh()->currentAgency->name);
    }

    public function test_a_user_can_create_another_agency(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->post(route('agencies.store'), ['name' => 'Yeni Ajans'])->assertRedirect(route('dashboard'));

        $this->assertSame('Yeni Ajans', $user->fresh()->currentAgency->name);
        $this->assertCount(2, $user->agencies);
    }
}
