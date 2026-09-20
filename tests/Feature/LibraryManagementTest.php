<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookPurchase;
use App\Models\Loan;
use App\Models\Order;
use App\Models\ReadingDocument;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PdfFirstPagePreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibraryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function book(int $available = 2): Book
    {
        $author = Author::create(['name' => 'Auteur Test', 'slug' => 'auteur-test']);
        $category = BookCategory::create(['name' => 'Sciences', 'slug' => 'sciences']);

        return Book::create(['author_id' => $author->id, 'book_category_id' => $category->id, 'title' => 'Livre de test', 'slug' => 'livre-de-test', 'price' => 12000, 'total_copies' => $available, 'available_copies' => $available]);
    }

    public function test_member_can_borrow_available_book_and_stock_is_decremented(): void
    {
        $member = User::factory()->create();
        $book = $this->book();

        $this->actingAs($member)->postJson('/api/loans', ['book_id' => $book->id])
            ->assertCreated()
            ->assertJsonPath('loan.fee_amount', 600)
            ->assertJsonPath('loan.fee_status', 'pending');

        $this->assertDatabaseHas('loans', ['user_id' => $member->id, 'book_id' => $book->id, 'status' => 'borrowed', 'fee_amount' => 600, 'fee_status' => 'pending']);
        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_member_can_remove_a_returned_loan_from_their_list_without_deleting_history(): void
    {
        $member = User::factory()->create();
        $book = $this->book();
        $loan = Loan::create([
            'user_id' => $member->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(2),
            'due_at' => now()->addDays(28),
            'returned_at' => now()->subDay(),
            'status' => 'returned',
            'fee_amount' => 600,
            'fee_status' => 'paid',
        ]);

        $this->actingAs($member)->deleteJson("/api/loans/{$loan->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'status' => 'returned',
        ]);
        $this->assertNotNull($loan->fresh()->hidden_by_user_at);
        $this->actingAs($member)->getJson('/api/loans')->assertJsonMissing(['id' => $loan->id]);
    }

    public function test_member_can_purchase_a_book_at_full_price_and_download_it_after_payment(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'student']);
        $book = $this->book();
        $document = ReadingDocument::create([
            'uploaded_by' => $admin->id,
            'book_id' => $book->id,
            'title' => 'Livre numérique',
            'private_path' => 'reading-documents/book.pdf',
            'original_filename' => 'livre-numerique.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'is_active' => true,
        ]);
        Storage::disk('private')->put($document->private_path, '%PDF-1.4 test');

        $purchase = $this->actingAs($member)->postJson("/api/books/{$book->id}/purchase")
            ->assertOk()
            ->assertJsonPath('order.purpose', 'book_purchase')
            ->assertJsonPath('order.total', 12000)
            ->json();

        $order = Order::findOrFail($purchase['order']['id']);
        $this->actingAs($member)->get(route('books.download', $book))->assertForbidden();

        $this->actingAs($member)
            ->post(route('payments.initiate', [$order, 'fake']), $this->billingData())
            ->assertRedirect();

        $this->assertDatabaseHas('book_purchases', [
            'user_id' => $member->id,
            'book_id' => $book->id,
            'order_id' => $order->id,
        ]);
        $this->actingAs($member)
            ->get(route('books.download', $book))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=livre-numerique.pdf');
        $this->actingAs($member)
            ->get("/api/reading-documents/{$document->id}/read")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertInstanceOf(BookPurchase::class, BookPurchase::first());
    }

    public function test_member_must_pay_borrowing_fee_before_reading_borrowed_book_document(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'student']);
        $book = $this->book();
        $document = ReadingDocument::create([
            'uploaded_by' => $admin->id,
            'book_id' => $book->id,
            'title' => 'Lecture privée',
            'private_path' => 'reading-documents/private.pdf',
            'original_filename' => 'private.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'is_active' => true,
        ]);
        Storage::disk('private')->put($document->private_path, '%PDF-1.4 test');

        $loan = $this->actingAs($member)->postJson('/api/loans', ['book_id' => $book->id])
            ->assertCreated()
            ->json('loan');

        $this->actingAs($member)->get("/api/reading-documents/{$document->id}/read")->assertForbidden();
        $feeOrder = $this->actingAs($member)->postJson("/api/loans/{$loan['id']}/pay-fee")
            ->assertOk()
            ->assertJsonPath('loan.fee_status', 'pending')
            ->assertJsonPath('order.total', 600)
            ->json('order');

        $this->actingAs($member)->get("/api/reading-documents/{$document->id}/read")->assertForbidden();
        $this->actingAs($member)
            ->post(route('payments.initiate', [Order::findOrFail($feeOrder['id']), 'fake']), $this->billingData())
            ->assertRedirect(route('orders.show', $feeOrder['id']));
        $this->assertDatabaseHas('loans', ['id' => $loan['id'], 'fee_status' => 'paid']);
        $this->actingAs($member)->get("/api/reading-documents/{$document->id}/read")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_member_cannot_create_book_or_return_another_users_loan(): void
    {
        $member = User::factory()->create();
        $book = $this->book();
        $loan = Loan::create(['user_id' => User::factory()->create()->id, 'book_id' => $book->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(14), 'status' => 'borrowed']);

        $this->actingAs($member)->postJson('/api/books', ['title' => 'Interdit'])->assertForbidden();
        $this->actingAs($member)->postJson("/api/loans/{$loan->id}/return")->assertForbidden();
    }

    public function test_admin_can_modify_book_details_and_catalog_visibility(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $book = $this->book();

        $this->actingAs($admin)->putJson("/api/books/{$book->id}", [
            'title' => 'Livre administré',
            'total_copies' => 4,
            'is_active' => false,
        ])->assertOk();

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => 'Livre administré', 'total_copies' => 4, 'available_copies' => 4, 'is_active' => false]);
    }

    public function test_hidden_book_is_only_visible_to_administrator(): void
    {
        $book = $this->book();
        $book->update(['is_active' => false]);

        $this->getJson('/api/books')->assertJsonCount(0, 'data');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/books')->assertJsonCount(1, 'data');
    }

    public function test_book_cannot_be_borrowed_when_no_copy_is_available(): void
    {
        $member = User::factory()->create();
        $book = $this->book(0);

        $this->actingAs($member)->postJson('/api/loans', ['book_id' => $book->id])->assertUnprocessable();
        $this->assertSame(0, Loan::count());
    }

    public function test_member_can_cancel_then_create_a_new_reservation(): void
    {
        $member = User::factory()->create();
        $book = $this->book(0);

        $first = $this->actingAs($member)->postJson("/api/books/{$book->id}/reservations")->assertCreated()->json('reservation');
        $this->actingAs($member)->deleteJson("/api/reservations/{$first['id']}")->assertNoContent();
        $this->actingAs($member)->postJson("/api/books/{$book->id}/reservations")->assertCreated();
    }

    public function test_member_can_borrow_from_a_ready_reservation_and_pay_fee_afterwards(): void
    {
        $member = User::factory()->create();
        $book = $this->book(1);
        $reservation = Reservation::create([
            'user_id' => $member->id,
            'book_id' => $book->id,
            'reserved_at' => now(),
            'expires_at' => now()->addDays(3),
            'status' => 'ready',
        ]);

        $loan = $this->actingAs($member)->postJson('/api/loans', [
            'book_id' => $book->id,
            'reservation_id' => $reservation->id,
        ])
            ->assertCreated()
            ->assertJsonPath('loan.fee_amount', 600)
            ->assertJsonPath('loan.fee_status', 'pending')
            ->json('loan');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'fulfilled']);
        $feeOrder = $this->actingAs($member)->postJson("/api/loans/{$loan['id']}/pay-fee")
            ->assertOk()
            ->assertJsonPath('loan.fee_status', 'pending')
            ->assertJsonPath('order.total', 600)
            ->json('order');

        $this->actingAs($member)
            ->post(route('payments.initiate', [Order::findOrFail($feeOrder['id']), 'fake']), $this->billingData())
            ->assertRedirect(route('orders.show', $feeOrder['id']));
        $this->assertDatabaseHas('loans', ['id' => $loan['id'], 'fee_status' => 'paid']);
    }

    public function test_borrowing_fee_is_confirmed_by_real_provider_flow(): void
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
            'https://sandbox.mtn.test/collection/v1_0/requesttopay/*' => Http::response(['amount' => '600', 'currency' => 'XOF', 'status' => 'SUCCESSFUL'], 200),
        ]);

        $member = User::factory()->create();
        $book = $this->book();
        $loan = $this->actingAs($member)->postJson('/api/loans', ['book_id' => $book->id])->assertCreated()->json('loan');
        $feeOrder = $this->actingAs($member)->postJson("/api/loans/{$loan['id']}/pay-fee")->assertOk()->json('order');
        $order = Order::findOrFail($feeOrder['id']);

        $this->actingAs($member)
            ->post(route('payments.initiate', [$order, 'mtn']), $this->billingData(['payer_phone' => '22997000000']))
            ->assertRedirect();

        $this->assertDatabaseHas('loans', ['id' => $loan['id'], 'fee_status' => 'pending']);

        $this->actingAs($member)->get(route('orders.show', $order))->assertOk();

        $this->assertDatabaseHas('loans', ['id' => $loan['id'], 'fee_status' => 'paid', 'fee_provider' => 'mtn']);
    }

    public function test_admin_can_add_an_author_and_manage_a_reservation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'student']);
        $book = $this->book(1);
        $reservation = Reservation::create(['user_id' => $member->id, 'book_id' => $book->id, 'reserved_at' => now(), 'status' => 'waiting']);

        $this->actingAs($admin)->postJson('/api/authors', ['name' => 'Nouvel auteur'])->assertCreated();
        $this->patchJson("/api/reservations/{$reservation->id}/status", ['status' => 'ready'])->assertOk();
        $this->postJson('/api/admin/loans', ['user_id' => $member->id, 'book_id' => $book->id, 'reservation_id' => $reservation->id, 'due_days' => 21])->assertCreated();

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'fulfilled']);
        $this->assertDatabaseHas('loans', ['user_id' => $member->id, 'book_id' => $book->id, 'status' => 'borrowed']);
    }

    public function test_admin_can_upload_a_private_reading_document_while_member_cannot(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'student']);
        $book = $this->book();
        $file = UploadedFile::fake()->create('guide.pdf', 240, 'application/pdf');

        $this->actingAs($member)->post('/api/reading-documents', ['title' => 'Guide', 'file' => $file])->assertForbidden();
        $this->actingAs($admin)->post('/api/reading-documents', ['book_id' => $book->id, 'title' => 'Guide de consultation', 'file' => $file])->assertCreated();

        $document = ReadingDocument::firstOrFail();
        Storage::disk('private')->assertExists($document->private_path);
        $this->actingAs($member)->get("/api/reading-documents/{$document->id}/read")->assertForbidden();
        Loan::create(['user_id' => $member->id, 'book_id' => $book->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(30), 'status' => 'borrowed', 'fee_amount' => 600, 'fee_status' => 'paid', 'fee_paid_at' => now(), 'access_expires_at' => now()->addDays(30)]);
        $this->actingAs($member)->get("/api/reading-documents/{$document->id}/read")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_attach_a_private_reading_document_to_a_physical_book(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'student']);
        $book = $this->book();

        $this->actingAs($admin)->post('/api/reading-documents', [
            'book_id' => $book->id,
            'title' => 'Chapitre complémentaire',
            'file' => UploadedFile::fake()->create('chapitre.pdf', 240, 'application/pdf'),
        ])->assertCreated();

        $document = ReadingDocument::firstOrFail();
        $this->assertSame($book->id, $document->book_id);
        $this->actingAs($member)->getJson('/api/reading-documents')
            ->assertOk()
            ->assertJsonPath('documents.0.book_id', $book->id);
        Loan::create(['user_id' => $member->id, 'book_id' => $book->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(30), 'status' => 'borrowed', 'fee_amount' => 600, 'fee_status' => 'paid', 'fee_paid_at' => now(), 'access_expires_at' => now()->addDays(30)]);
        $this->actingAs($member)->get("/api/reading-documents/{$document->id}/read")
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename="chapitre.pdf"');
    }

    public function test_active_book_document_exposes_only_first_page_preview_before_fee_payment(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'student']);
        $book = $this->book();
        $document = ReadingDocument::create([
            'uploaded_by' => $admin->id,
            'book_id' => $book->id,
            'title' => 'Aperçu public',
            'private_path' => 'reading-documents/preview.pdf',
            'original_filename' => 'preview.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'is_active' => true,
        ]);
        Storage::disk('private')->put($document->private_path, '%PDF-1.4 test');
        $this->fakePreviewService('previews/reading-document.png');

        $this->get("/api/reading-documents/{$document->id}/preview")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        Loan::create(['user_id' => $member->id, 'book_id' => $book->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(14), 'status' => 'borrowed', 'fee_amount' => 600, 'fee_status' => 'pending']);
        $this->actingAs($member)->get("/api/reading-documents/{$document->id}/read")->assertForbidden();
    }

    public function test_paid_borrowed_document_reader_serves_rendered_pages_only_after_fee_payment(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'student']);
        $book = $this->book();
        $document = ReadingDocument::create([
            'uploaded_by' => $admin->id,
            'book_id' => $book->id,
            'title' => 'Document rendu',
            'private_path' => 'reading-documents/rendered.pdf',
            'original_filename' => 'rendered.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'is_active' => true,
        ]);
        Storage::disk('private')->put($document->private_path, '%PDF-1.4 test');
        Storage::disk('private')->put('reader-pages/reading-document.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/akQj6QAAAAASUVORK5CYII='));
        $this->fakePreviewService('reader-pages/reading-document.png');

        Loan::create(['user_id' => $member->id, 'book_id' => $book->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(14), 'status' => 'borrowed', 'fee_amount' => 600, 'fee_status' => 'pending']);
        $this->actingAs($member)->getJson("/api/reading-documents/{$document->id}/reader")->assertForbidden();
        $this->actingAs($member)->get("/api/reading-documents/{$document->id}/pages/1")->assertForbidden();

        Loan::query()->update(['fee_status' => 'paid', 'fee_paid_at' => now(), 'access_expires_at' => now()->addDays(30)]);
        $this->actingAs($member)
            ->getJson("/api/reading-documents/{$document->id}/reader")
            ->assertOk()
            ->assertJsonPath('document.pages.0.number', 1);
        $this->actingAs($member)
            ->get("/api/reading-documents/{$document->id}/pages/1")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_borrowed_document_reader_expires_after_thirty_days(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'student']);
        $book = $this->book();
        $document = ReadingDocument::create([
            'uploaded_by' => $admin->id,
            'book_id' => $book->id,
            'title' => 'Document expiré',
            'private_path' => 'reading-documents/expired.pdf',
            'original_filename' => 'expired.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'is_active' => true,
        ]);
        Storage::disk('private')->put($document->private_path, '%PDF-1.4 test');
        Loan::create([
            'user_id' => $member->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(31),
            'due_at' => now()->subDay(),
            'status' => 'borrowed',
            'fee_amount' => 600,
            'fee_status' => 'paid',
            'fee_paid_at' => now()->subDays(31),
            'access_expires_at' => now()->subDay(),
        ]);

        $this->actingAs($member)->getJson("/api/reading-documents/{$document->id}/reader")->assertForbidden();
    }

    public function test_immediate_return_refunds_full_borrowing_fee(): void
    {
        $member = User::factory()->create();
        $book = $this->book();
        $book->decrement('available_copies');
        $loan = Loan::create([
            'user_id' => $member->id,
            'book_id' => $book->id,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(30),
            'status' => 'borrowed',
            'fee_amount' => 600,
            'fee_currency' => 'XOF',
            'fee_status' => 'paid',
            'fee_paid_at' => now(),
            'access_expires_at' => now()->addDays(30),
            'fee_provider' => 'fake',
        ]);

        $this->actingAs($member)->postJson("/api/loans/{$loan->id}/return")
            ->assertOk()
            ->assertJsonPath('loan.fee_charged_amount', 0)
            ->assertJsonPath('loan.fee_refund_amount', 600)
            ->assertJsonPath('loan.fee_refund_status', 'refunded');
    }

    public function test_return_after_some_days_charges_prorated_borrowing_fee(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(10));
        $member = User::factory()->create();
        $book = $this->book();
        $book->decrement('available_copies');
        $loan = Loan::create([
            'user_id' => $member->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(5),
            'due_at' => now()->addDays(25),
            'status' => 'borrowed',
            'fee_amount' => 600,
            'fee_currency' => 'XOF',
            'fee_status' => 'paid',
            'fee_paid_at' => now()->subDays(5),
            'access_expires_at' => now()->addDays(25),
            'fee_provider' => 'mtn',
        ]);

        $this->actingAs($member)->postJson("/api/loans/{$loan->id}/return")
            ->assertOk()
            ->assertJsonPath('loan.fee_charged_amount', 100)
            ->assertJsonPath('loan.fee_refund_amount', 500)
            ->assertJsonPath('loan.fee_refund_status', 'pending');
    }

    public function test_admin_can_see_active_readers_but_members_cannot_access_presence_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['name' => 'Lecteur connecté', 'role' => 'student']);
        $book = $this->book();
        $document = ReadingDocument::create([
            'uploaded_by' => $admin->id,
            'book_id' => $book->id,
            'title' => 'Document de lecture',
            'private_path' => 'reading-documents/test.pdf',
            'original_filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'is_active' => true,
        ]);

        Loan::create(['user_id' => $member->id, 'book_id' => $book->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(30), 'status' => 'borrowed', 'fee_amount' => 600, 'fee_status' => 'paid', 'fee_paid_at' => now(), 'access_expires_at' => now()->addDays(30)]);
        $this->actingAs($member)->post("/api/reading-documents/{$document->id}/presence")->assertNoContent();
        $this->actingAs($member)->getJson('/api/reading-presences')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/reading-presences')
            ->assertOk()
            ->assertJsonPath('presences.0.user.name', 'Lecteur connecté')
            ->assertJsonPath('presences.0.document.title', 'Document de lecture');
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
