<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_operational_dashboard_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'student']);
        $order = Order::create(['user_id' => $customer->id, 'reference' => 'CMD-ADMIN-1', 'subtotal' => 2500, 'discount' => 0, 'total' => 2500, 'currency' => 'XOF', 'status' => 'awaiting_payment']);
        Payment::create(['order_id' => $order->id, 'provider' => 'fake', 'environment' => 'test', 'internal_reference' => 'PAY-ADMIN-1', 'amount' => 2500, 'currency' => 'XOF', 'status' => 'pending']);
        WebhookEvent::create(['provider' => 'fake', 'environment' => 'test', 'event_id' => 'evt-admin-1', 'signature_valid' => true, 'payload_hash' => hash('sha256', 'payload'), 'received_at' => now(), 'status' => 'processed', 'processed_at' => now()]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Tableau de bord opérationnel');
        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk()->assertSee('CMD-ADMIN-1');
        $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk()->assertSee('PAY-ADMIN-1');
        $this->actingAs($admin)->get(route('admin.webhooks.index'))->assertOk()->assertSee('evt-admin-1');
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee($customer->email);
    }

    public function test_student_cannot_open_administration_pages(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.payments.index'))->assertForbidden();
    }
}
