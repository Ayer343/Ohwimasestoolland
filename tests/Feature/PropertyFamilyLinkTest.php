<?php
// tests/Feature/PropertyFamilyLinkTest.php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyFamilyLink;
use App\Models\User;
use Tests\TestCase;

class PropertyFamilyLinkTest extends TestCase
{
    public function test_landlord_can_propose_a_family_member(): void
    {
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->for($landlord, 'landlord')->create();

        $response = $this->actingAs($landlord)->post(route('landlord.family-links.store'), [
            'property_id'   => $property->id,
            'proposed_name' => 'Jane Doe',
            'proposed_phone'=> '+233555000111',
            'relationship'  => 'spouse',
            'permissions'   => ['view', 'receive_notifications'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('property_family_links', [
            'property_id'   => $property->id,
            'proposed_name' => 'Jane Doe',
            'status'        => 'pending',
        ]);
    }

    public function test_landlord_cannot_propose_for_someone_elses_property(): void
    {
        $landlord = User::factory()->landlord()->create();
        $other    = User::factory()->landlord()->create();
        $property = Property::factory()->for($other, 'landlord')->create();

        $this->actingAs($landlord)->post(route('landlord.family-links.store'), [
            'property_id'   => $property->id,
            'proposed_name' => 'Sneaky',
            'proposed_phone'=> '+233555000222',
            'relationship'  => 'other',
        ])->assertStatus(422);
    }

    public function test_admin_can_approve_a_pending_link(): void
    {
        $admin  = User::factory()->admin()->create();
        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->for($landlord, 'landlord')->create();

        $link = PropertyFamilyLink::factory()->for($property)->for($landlord, 'landlord')->create([
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.family-links.review', $link), [
            'decision'    => 'approve',
            'permissions' => ['view'],
        ])->assertRedirect();

        $link->refresh();
        $this->assertEquals('approved', $link->status);
        $this->assertNotNull($link->linked_user_id);
    }

    public function test_max_links_per_property_is_enforced(): void
    {
        config(['property_family_links.max_links_per_property' => 2]);

        $landlord = User::factory()->landlord()->create();
        $property = Property::factory()->for($landlord, 'landlord')->create();

        PropertyFamilyLink::factory()->count(2)->for($property)->for($landlord, 'landlord')
            ->create(['status' => 'approved']);

        $this->actingAs($landlord)->post(route('landlord.family-links.store'), [
            'property_id'    => $property->id,
            'proposed_name'  => 'Third',
            'proposed_phone' => '+233555000999',
            'relationship'   => 'son',
        ])->assertSessionHasErrors('property_id');
    }
}