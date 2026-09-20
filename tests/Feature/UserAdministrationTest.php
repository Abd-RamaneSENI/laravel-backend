<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_assign_a_role_and_delete_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->getJson('/api/users')->assertOk()->assertJsonFragment(['id' => $member->id]);
        $this->patchJson("/api/users/{$member->id}/role", ['role' => 'vendor'])->assertOk()->assertJsonPath('user.role', 'vendor');
        $this->deleteJson("/api/users/{$member->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
    }

    public function test_member_cannot_manage_users(): void
    {
        $member = User::factory()->create(['role' => 'student']);
        $otherMember = User::factory()->create(['role' => 'student']);

        $this->actingAs($member)->getJson('/api/users')->assertForbidden();
        $this->patchJson("/api/users/{$otherMember->id}/role", ['role' => 'admin'])->assertForbidden();
        $this->deleteJson("/api/users/{$otherMember->id}")->assertForbidden();
    }

    public function test_admin_cannot_delete_their_own_account_or_remove_the_last_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->deleteJson("/api/users/{$admin->id}")->assertUnprocessable();
        $this->patchJson("/api/users/{$admin->id}/role", ['role' => 'student'])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }
}
