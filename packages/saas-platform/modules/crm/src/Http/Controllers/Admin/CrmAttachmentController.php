<?php

namespace SaaSPlatform\Modules\Crm\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Crm\Http\Controllers\Concerns\ResolvesCrmSubject;
use SaaSPlatform\Modules\Crm\Models\CrmAttachment;

class CrmAttachmentController extends Controller
{
    use ResolvesCrmSubject;

    public function store(Request $request, int $siteId, string $subjectType, int $subjectId)
    {
        $subject = $this->resolveSubject($subjectType, $subjectId, $siteId);

        $allowed = implode(',', config('crm.attachments.allowed', []));
        $maxKb = (int) config('crm.attachments.max_size', 5120);

        $request->validate([
            'file' => "required|file|max:{$maxKb}|mimes:{$allowed}",
        ]);

        $disk = config('crm.attachments.disk', 'private');
        $file = $request->file('file');
        $path = $file->store(config('crm.attachments.path', 'crm-attachments'), $disk);

        $subject->attachments()->create([
            'site_id' => $siteId,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'فایل بارگذاری شد.');
    }

    public function destroy(int $siteId, CrmAttachment $attachment)
    {
        abort_unless($attachment->site_id === $siteId, 404);

        \Illuminate\Support\Facades\Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'فایل حذف شد.');
    }
}
