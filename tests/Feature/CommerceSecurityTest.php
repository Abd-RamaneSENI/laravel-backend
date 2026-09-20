<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Cycle;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\PdfFirstPagePreview;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CommerceSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function resource(User $author): Resource
    {
        $cycle = Cycle::create(['name' => 'Lycée', 'slug' => 'lycee']);
        $class = SchoolClass::create(['cycle_id' => $cycle->id, 'name' => 'Terminale', 'slug' => 'terminale']);
        $subject = Subject::create(['name' => 'Mathématiques', 'slug' => 'mathematiques']);
        $type = ResourceType::create(['name' => 'Cours', 'slug' => 'cours']);

        return Resource::create(['author_id' => $author->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id, 'resource_type_id' => $type->id, 'title' => 'Cours de référence', 'slug' => 'cours-reference', 'description' => 'Contenu de test autorisé.', 'price' => 1500, 'status' => 'published', 'visibility' => 'public', 'private_path' => 'resources/test.pdf', 'original_filename' => 'cours-reference.pdf', 'mime_type' => 'application/pdf']);
    }

    public function test_checkout_uses_price_from_database_not_browser(): void
    {
        $buyer = User::factory()->create();
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        $cart = Cart::create(['user_id' => $buyer->id]);
        $cart->items()->create(['resource_id' => $resource->id]);
        $this->actingAs($buyer)->post(route('checkout.store'), ['total' => 1])->assertRedirect();
        $this->assertSame(1500, Order::firstOrFail()->total);
    }

    public function test_checkout_with_empty_cart_returns_to_cart_with_a_clear_message(): void
    {
        $buyer = User::factory()->create();
        Cart::create(['user_id' => $buyer->id]);
        $this->actingAs($buyer)->from(route('cart.show'))->post(route('checkout.store'))->assertRedirect(route('cart.show'))->assertSessionHasErrors('cart');
    }

    public function test_duplicate_valid_webhook_only_grants_one_entitlement(): void
    {
        config(['payments.environment' => 'test', 'payments.enabled' => ['fake']]);
        $buyer = User::factory()->create();
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        $order = Order::create(['user_id' => $buyer->id, 'reference' => 'CMD-TEST', 'subtotal' => 1500, 'discount' => 0, 'total' => 1500, 'currency' => 'XOF', 'status' => 'awaiting_payment']);
        $order->items()->create(['resource_id' => $resource->id, 'title_snapshot' => $resource->title, 'unit_price' => 1500, 'quantity' => 1, 'total' => 1500]);
        $payment = Payment::create(['order_id' => $order->id, 'provider' => 'fake', 'environment' => 'test', 'external_reference' => 'FAKE-1', 'internal_reference' => 'PAY-1', 'amount' => 1500, 'currency' => 'XOF', 'status' => 'pending']);
        $payload = ['event_id' => 'evt-1', 'reference' => $payment->external_reference, 'amount' => 1500, 'currency' => 'XOF', 'status' => 'succeeded', 'test_token' => 'seni-cnf-test'];
        $this->postJson(route('webhooks.store', 'fake'), $payload)->assertOk();
        $this->postJson(route('webhooks.store', 'fake'), $payload)->assertOk();
        $this->assertSame(1, Entitlement::count());
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'succeeded']);
    }

    public function test_minimal_webhook_uses_server_verification_before_granting_access(): void
    {
        config(['payments.environment' => 'test', 'payments.enabled' => ['fake']]);
        $buyer = User::factory()->create();
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        $order = Order::create(['user_id' => $buyer->id, 'reference' => 'CMD-MIN-WEBHOOK', 'subtotal' => 1500, 'discount' => 0, 'total' => 1500, 'currency' => 'XOF', 'status' => 'awaiting_payment']);
        $order->items()->create(['resource_id' => $resource->id, 'title_snapshot' => $resource->title, 'unit_price' => 1500, 'quantity' => 1, 'total' => 1500]);
        Payment::create(['order_id' => $order->id, 'provider' => 'fake', 'environment' => 'test', 'internal_reference' => 'PAY-MINIMAL', 'amount' => 1500, 'currency' => 'XOF', 'status' => 'pending']);

        $payload = ['event_id' => 'evt-minimal', 'reference' => 'PAY-MINIMAL', 'status' => 'succeeded', 'test_token' => 'seni-cnf-test'];
        $this->postJson(route('webhooks.store', 'fake'), $payload)->assertOk();

        $this->assertSame(1, Entitlement::where('user_id', $buyer->id)->where('resource_id', $resource->id)->count());
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'fulfilled']);
    }

    public function test_pending_webhook_does_not_grant_access(): void
    {
        config(['payments.environment' => 'test', 'payments.enabled' => ['fake']]);
        $buyer = User::factory()->create();
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        $order = Order::create(['user_id' => $buyer->id, 'reference' => 'CMD-PENDING-WEBHOOK', 'subtotal' => 1500, 'discount' => 0, 'total' => 1500, 'currency' => 'XOF', 'status' => 'awaiting_payment']);
        $order->items()->create(['resource_id' => $resource->id, 'title_snapshot' => $resource->title, 'unit_price' => 1500, 'quantity' => 1, 'total' => 1500]);
        $payment = Payment::create(['order_id' => $order->id, 'provider' => 'fake', 'environment' => 'test', 'external_reference' => 'FAKE-PENDING', 'internal_reference' => 'PAY-PENDING', 'amount' => 1500, 'currency' => 'XOF', 'status' => 'pending']);

        $payload = ['event_id' => 'evt-pending', 'reference' => $payment->external_reference, 'amount' => 1500, 'currency' => 'XOF', 'status' => 'pending', 'test_token' => 'seni-cnf-test'];
        $this->postJson(route('webhooks.store', 'fake'), $payload)->assertOk();

        $this->assertSame(0, Entitlement::where('user_id', $buyer->id)->where('resource_id', $resource->id)->count());
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    public function test_fake_payment_immediately_grants_library_access_and_auto_download_redirect(): void
    {
        config(['payments.environment' => 'test', 'payments.enabled' => ['fake']]);
        $buyer = User::factory()->create();
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        $order = Order::create(['user_id' => $buyer->id, 'reference' => 'CMD-PAID-NOW', 'subtotal' => 1500, 'discount' => 0, 'total' => 1500, 'currency' => 'XOF', 'status' => 'pending']);
        $order->items()->create(['resource_id' => $resource->id, 'title_snapshot' => $resource->title, 'unit_price' => 1500, 'quantity' => 1, 'total' => 1500]);

        $this->actingAs($buyer)
            ->post(route('payments.initiate', [$order, 'fake']), $this->billingData())
            ->assertRedirect(route('library.index', ['download_order' => $order->id]));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'fulfilled']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'provider' => 'fake', 'status' => 'succeeded']);
        $this->assertSame(1, Entitlement::where('user_id', $buyer->id)->where('resource_id', $resource->id)->count());
        $this->assertSame('Awa Mensah', $order->fresh()->billing_name);
        $this->assertSame('facture@example.com', $order->fresh()->billing_email);
        $this->assertNotNull($order->fresh()->invoice_sent_at);
        $this->actingAs($buyer)->get(route('orders.invoice', $order))->assertOk()->assertSee('Facture');
    }

    public function test_paid_invoice_can_be_opened_and_resent_without_rate_limit_error(): void
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'user_id' => $buyer->id,
            'reference' => 'CMD-INVOICE-RESEND',
            'subtotal' => 1500,
            'discount' => 0,
            'total' => 1500,
            'currency' => 'XOF',
            'status' => 'fulfilled',
            'billing_name' => 'Awa Mensah',
            'billing_email' => 'facture@example.com',
            'paid_at' => now(),
            'fulfilled_at' => now(),
        ]);
        Payment::create(['order_id' => $order->id, 'provider' => 'fake', 'environment' => 'test', 'internal_reference' => 'PAY-INVOICE-RESEND', 'amount' => 1500, 'currency' => 'XOF', 'status' => 'succeeded', 'paid_at' => now()]);

        $this->actingAs($buyer)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Ouvrir la facture')
            ->assertSee('Envoyer la facture à mon e-mail');

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($buyer)
                ->post(route('orders.invoice.resend', $order))
                ->assertRedirect(route('orders.invoice', $order));
        }

        $this->actingAs($buyer)
            ->get(route('orders.invoice.resend.open', $order))
            ->assertRedirect(route('orders.invoice', $order));
    }

    public function test_failed_invoice_delivery_is_recorded_for_automatic_retry(): void
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'user_id' => $buyer->id,
            'reference' => 'CMD-INVOICE-FAILED',
            'subtotal' => 1500,
            'discount' => 0,
            'total' => 1500,
            'currency' => 'XOF',
            'status' => 'fulfilled',
            'billing_name' => 'Awa Mensah',
            'billing_email' => 'facture@example.com',
            'paid_at' => now(),
            'fulfilled_at' => now(),
        ]);

        Mail::shouldReceive('html')
            ->once()
            ->andThrow(new RuntimeException('Connection could not be established with host "smtp.gmail.com:587".'));

        $this->assertFalse(app(PaymentService::class)->sendInvoiceEmail($order, true));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'invoice_email_attempts' => 1,
            'invoice_email_error' => 'Connection could not be established with host "smtp.gmail.com:587".',
        ]);
    }

    public function test_mtn_payment_grants_access_only_after_server_confirmation(): void
    {
        config([
            'payments.environment' => 'test',
            'payments.enabled' => ['mtn'],
            'payments.providers.mtn.test.base_url' => 'https://sandbox.mtn.test',
            'payments.providers.mtn.test.api_user' => 'api-user',
            'payments.providers.mtn.test.api_key' => 'api-key',
            'payments.providers.mtn.test.subscription_key' => 'subscription-key',
            'payments.providers.mtn.test.target_environment' => 'sandbox',
            'payments.providers.mtn.test.callback_url' => 'https://seni.test/webhooks/mtn',
        ]);
        Http::fake([
            'https://sandbox.mtn.test/collection/token/' => Http::response(['access_token' => 'token'], 200),
            'https://sandbox.mtn.test/collection/v1_0/requesttopay' => Http::response(null, 202),
            'https://sandbox.mtn.test/collection/v1_0/requesttopay/*' => Http::response(['amount' => '1500', 'currency' => 'XOF', 'status' => 'SUCCESSFUL'], 200),
        ]);

        $buyer = User::factory()->create();
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        $order = Order::create(['user_id' => $buyer->id, 'reference' => 'CMD-MTN-1', 'subtotal' => 1500, 'discount' => 0, 'total' => 1500, 'currency' => 'XOF', 'status' => 'pending']);
        $order->items()->create(['resource_id' => $resource->id, 'title_snapshot' => $resource->title, 'unit_price' => 1500, 'quantity' => 1, 'total' => 1500]);

        $this->actingAs($buyer)
            ->post(route('payments.initiate', [$order, 'mtn']), $this->billingData(['payer_phone' => '22997000000']))
            ->assertRedirect();

        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'provider' => 'mtn', 'status' => 'pending']);
        $this->assertSame(0, Entitlement::where('user_id', $buyer->id)->where('resource_id', $resource->id)->count());

        $this->actingAs($buyer)->get(route('orders.show', $order))->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'fulfilled']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'provider' => 'mtn', 'status' => 'succeeded']);
        $this->assertSame(1, Entitlement::where('user_id', $buyer->id)->where('resource_id', $resource->id)->count());
    }

    public function test_real_provider_without_required_configuration_is_refused_cleanly(): void
    {
        config(['payments.environment' => 'test', 'payments.enabled' => ['mtn'], 'payments.providers.mtn.test.base_url' => null]);
        $buyer = User::factory()->create();
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        $order = Order::create(['user_id' => $buyer->id, 'reference' => 'CMD-MTN-MISSING', 'subtotal' => 1500, 'discount' => 0, 'total' => 1500, 'currency' => 'XOF', 'status' => 'pending']);
        $order->items()->create(['resource_id' => $resource->id, 'title_snapshot' => $resource->title, 'unit_price' => 1500, 'quantity' => 1, 'total' => 1500]);

        $this->actingAs($buyer)
            ->from(route('orders.show', $order))
            ->post(route('payments.initiate', [$order, 'mtn']), $this->billingData(['payer_phone' => '22997000000']))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('payment');

        $this->assertSame(0, Payment::count());
    }

    public function test_a_user_cannot_download_another_users_resource(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        $order = Order::create(['user_id' => $owner->id, 'reference' => 'CMD-OWNER', 'subtotal' => 1500, 'discount' => 0, 'total' => 1500, 'currency' => 'XOF', 'status' => 'fulfilled']);
        Entitlement::create(['user_id' => $owner->id, 'resource_id' => $resource->id, 'order_id' => $order->id, 'granted_at' => now()]);
        $this->actingAs($other)->get(route('resources.download', $resource))->assertForbidden();
    }

    public function test_buyer_can_read_purchased_pdf_on_platform_but_other_user_cannot(): void
    {
        Storage::fake('private');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        Storage::disk('private')->put($resource->private_path, "%PDF-1.4\n% Test PDF");
        $order = Order::create(['user_id' => $owner->id, 'reference' => 'CMD-READ', 'subtotal' => 1500, 'discount' => 0, 'total' => 1500, 'currency' => 'XOF', 'status' => 'fulfilled']);
        Entitlement::create(['user_id' => $owner->id, 'resource_id' => $resource->id, 'order_id' => $order->id, 'granted_at' => now()]);

        $this->actingAs($owner)->get(route('resources.read', $resource))->assertOk()->assertSee('Lecture en ligne')->assertSee($resource->title);
        $this->actingAs($owner)->get(route('resources.inline', $resource))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($other)->get(route('resources.read', $resource))->assertForbidden();
        $this->actingAs($other)->get(route('resources.inline', $resource))->assertForbidden();
    }

    public function test_public_catalog_only_exposes_first_page_preview_before_purchase(): void
    {
        Storage::fake('private');
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        Storage::disk('private')->put($resource->private_path, '%PDF-1.4 test');
        $this->fakePreviewService('previews/resource.png');

        $this->get(route('resources.preview', $resource->slug))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->actingAs(User::factory()->create())
            ->get(route('resources.inline', $resource))
            ->assertForbidden();
    }

    public function test_purchased_pdf_reader_uses_white_page_images_without_exposing_pages_to_others(): void
    {
        Storage::fake('private');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        Storage::disk('private')->put($resource->private_path, '%PDF-1.4 test');
        $this->fakePreviewService('reader-pages/resource-page.png');
        $order = Order::create(['user_id' => $owner->id, 'reference' => 'CMD-READER', 'subtotal' => 1500, 'discount' => 0, 'total' => 1500, 'currency' => 'XOF', 'status' => 'fulfilled']);
        Entitlement::create(['user_id' => $owner->id, 'resource_id' => $resource->id, 'order_id' => $order->id, 'granted_at' => now()]);

        $this->actingAs($owner)
            ->get(route('resources.read', $resource))
            ->assertOk()
            ->assertSee(route('resources.page', ['resource' => $resource, 'page' => 1]), false);
        $this->actingAs($owner)
            ->get(route('resources.page', ['resource' => $resource, 'page' => 1]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
        $this->actingAs($other)
            ->get(route('resources.page', ['resource' => $resource, 'page' => 1]))
            ->assertForbidden();
    }

    public function test_admin_uploads_resource_to_private_storage(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create(['role' => 'admin']);
        $type = ResourceType::create(['name' => 'Cours', 'slug' => 'cours']);
        $this->actingAs($admin)->post(route('admin.resources.store'), ['title' => 'Cours sécurisé', 'description' => 'Document de test autorisé.', 'resource_type_id' => $type->id, 'price' => 1200, 'rights_statement' => 'Droits de diffusion confirmés.', 'file' => UploadedFile::fake()->create('cours.pdf', 12, 'application/pdf')])->assertRedirect(route('admin.resources.index'));
        $resource = Resource::where('title', 'Cours sécurisé')->firstOrFail();
        $this->assertStringStartsWith('resources/', $resource->private_path);
        Storage::disk('private')->assertExists($resource->private_path);
    }

    public function test_admin_can_modify_resource_and_replace_its_private_file(): void
    {
        Storage::fake('private');
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        Storage::disk('private')->put($resource->private_path, 'ancienne-version');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.resources.update', $resource), ['title' => 'Cours mis à jour', 'description' => 'Version mise à jour du contenu autorisé.', 'school_class_id' => $resource->school_class_id, 'subject_id' => $resource->subject_id, 'resource_type_id' => $resource->resource_type_id, 'price' => 2200, 'status' => 'published', 'rights_statement' => 'Droits confirmés.', 'file' => UploadedFile::fake()->create('nouvelle-version.pdf', 12, 'application/pdf')])->assertRedirect(route('admin.resources.index'));
        $updated = $resource->fresh();
        $this->assertSame('Cours mis à jour', $updated->title);
        $this->assertSame(2200, $updated->price);
        $this->assertNotSame('resources/test.pdf', $updated->private_path);
        Storage::disk('private')->assertExists($updated->private_path);
        Storage::disk('private')->assertMissing('resources/test.pdf');
    }

    public function test_non_admin_cannot_modify_catalog_resource(): void
    {
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.resources.edit', $resource))->assertForbidden();
    }

    public function test_paid_resources_are_available_in_the_unified_catalog_api(): void
    {
        $resource = $this->resource(User::factory()->create(['role' => 'vendor']));
        $response = $this->getJson('/api/resources')
            ->assertOk()
            ->assertJsonPath('resources.0.id', $resource->id)
            ->assertJsonPath('resources.0.slug', $resource->slug);

        $previewUrl = $response->json('resources.0.preview_url');
        $this->assertStringStartsWith(route('resources.preview', $resource->slug), $previewUrl);
        $this->assertStringContainsString('v=', $previewUrl);

        $this->get('/catalogue')->assertRedirect(route('application'));
    }

    private function fakePreviewService(string $path): void
    {
        Storage::disk('private')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/akQj6QAAAAASUVORK5CYII='));
        $this->app->instance(PdfFirstPagePreview::class, new class($path) extends PdfFirstPagePreview
        {
            public function __construct(private readonly string $path) {}

            public function previewPath(string $privatePath, string $cacheKey): ?string
            {
                return $this->path;
            }

            public function pagePath(string $privatePath, string $cacheKey, int $page = 1, string $directory = 'reader-pages', int $resolution = 150): ?string
            {
                return $this->path;
            }

            public function pageCount(string $privatePath): ?int
            {
                return 1;
            }
        });
    }

    private function billingData(array $overrides = []): array
    {
        return $overrides + [
            'billing_name' => 'Awa Mensah',
            'billing_email' => 'facture@example.com',
        ];
    }
}
