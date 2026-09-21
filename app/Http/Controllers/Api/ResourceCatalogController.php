<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Resource;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResourceCatalogController extends Controller
{
    public function index(Request $request)
    {
        $resources = Resource::published()
            ->with(['schoolClass.cycle:id,name', 'subject:id,name', 'resourceType:id,name'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim((string) $request->input('q'));
                $query->where(fn ($where) => $where
                    ->whereLike('title', "%{$term}%")
                    ->orWhereLike('description', "%{$term}%"));
            })
            ->when($request->filled('cycle'), fn ($query) => $query->whereHas('schoolClass.cycle', fn ($cycle) => $cycle->where('slug', $request->input('cycle'))))
            ->latest('published_at')
            ->take(24)
            ->get(['id', 'school_class_id', 'subject_id', 'resource_type_id', 'title', 'slug', 'description', 'price', 'currency', 'academic_year', 'mime_type', 'original_filename', 'private_path', 'checksum', 'updated_at'])
            ->map(fn (Resource $resource) => [
                'id' => $resource->id,
                'school_class' => $resource->schoolClass,
                'subject' => $resource->subject,
                'resource_type' => $resource->resourceType,
                'title' => $resource->title,
                'slug' => $resource->slug,
                'description' => $resource->description,
                'price' => $resource->price,
                'currency' => $resource->currency,
                'academic_year' => $resource->academic_year,
                'preview_url' => route('resources.preview', [
                    'resource' => $resource->slug,
                    'v' => $resource->checksum ?: ($resource->updated_at?->timestamp ?? $resource->id),
                ]),
            ]);

        return response()->json(['resources' => $resources]);
    }

    public function purchase(Request $request, Resource $resource, AuditLogger $audit)
    {
        abort_unless($resource->status === 'published' && $resource->visibility === 'public', 404);
        abort_unless($resource->private_path, 422, 'Ce document ne possède pas encore de fichier disponible à l’achat.');

        $alreadyPaid = Entitlement::query()
            ->where('user_id', $request->user()->id)
            ->where('resource_id', $resource->id)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        if ($alreadyPaid) {
            return response()->json([
                'already_paid' => true,
                'download_url' => route('resources.download', $resource),
            ]);
        }

        $order = DB::transaction(function () use ($request, $resource): Order {
            $order = Order::query()
                ->where('user_id', $request->user()->id)
                ->where('purpose', 'resource_purchase')
                ->whereIn('status', ['pending', 'awaiting_payment'])
                ->whereHas('items', fn ($query) => $query->where('resource_id', $resource->id))
                ->whereDoesntHave('items', fn ($query) => $query->where('resource_id', '!=', $resource->id))
                ->lockForUpdate()
                ->first();

            if ($order) {
                return $order;
            }

            $price = (int) $resource->price;
            $currency = $resource->currency ?: config('payments.currency', 'XOF');
            $order = Order::create([
                'user_id' => $request->user()->id,
                'purpose' => 'resource_purchase',
                'reference' => 'CMD-'.strtoupper(Str::random(14)),
                'subtotal' => $price,
                'discount' => 0,
                'total' => $price,
                'currency' => $currency,
                'billing_name' => $request->user()->name,
                'billing_email' => $request->user()->email,
                'status' => 'pending',
            ]);

            $order->items()->create([
                'resource_id' => $resource->id,
                'title_snapshot' => $resource->title,
                'unit_price' => $price,
                'quantity' => 1,
                'total' => $price,
            ]);

            return $order;
        });

        $audit->record($request, 'resource.purchase_requested', $resource, [
            'order_id' => $order->id,
            'amount' => $order->total,
        ]);

        return response()->json([
            'order' => $order->load('items.resource'),
            'payment_url' => route('orders.show', $order),
            'download_url' => null,
        ]);
    }
}
