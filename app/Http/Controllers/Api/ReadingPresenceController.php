<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReadingDocument;
use App\Models\ReadingPresence;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ReadingPresenceController extends Controller
{
    public function touch(Request $request, ReadingDocument $document)
    {
        abort_unless($document->is_active || $request->user()->isAdmin(), 404);
        abort_unless($document->isReadableBy($request->user()), 403, 'Empruntez d’abord le livre associé pour lire ce document.');

        ReadingPresence::query()->where('last_seen_at', '<', now()->subHours(12))->delete();
        $presence = ReadingPresence::firstOrCreate(
            ['user_id' => $request->user()->id, 'reading_document_id' => $document->id],
            ['last_seen_at' => now(), 'started_at' => now()]
        );
        $presence->update(['last_seen_at' => now()]);

        return response()->noContent();
    }

    public function leave(Request $request, ReadingDocument $document)
    {
        ReadingPresence::query()
            ->where('user_id', $request->user()->id)
            ->where('reading_document_id', $document->id)
            ->delete();

        return response()->noContent();
    }

    public function index(Request $request, AuditLogger $audit)
    {
        ReadingPresence::query()->where('last_seen_at', '<', now()->subHours(12))->delete();
        $presences = ReadingPresence::query()
            ->online()
            ->with(['user:id,name,email', 'document:id,title'])
            ->latest('last_seen_at')
            ->get();

        $audit->record($request, 'reading_presence.viewed');

        return response()->json(['presences' => $presences]);
    }
}
