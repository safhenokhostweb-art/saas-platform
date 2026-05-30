<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Site;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class TicketSiteAttachmentController extends Controller
{
    public function download(string $path)
    {
        $path = ltrim($path, '/');

        if (str_contains($path, '..') || ! str_starts_with($path, 'ticket-attachments/')) {
            abort(403);
        }

        if (! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        return Storage::disk('private')->response($path);
    }
}
