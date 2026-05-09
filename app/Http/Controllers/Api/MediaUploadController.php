<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage_catalog') || $request->user()->hasPermission('manage_orders') || $request->user()->hasPermission('manage_issues'), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'collection' => ['required', 'string', 'in:product_image,template,artwork,issue_attachment,csv_import,claim_evidence'],
        ]);

        $file = $validated['file'];
        $tenantId = $request->user()->tenant_id;
        $disk = 'public';
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $path = $file->storeAs(
            'tenants/'.$tenantId.'/'.$validated['collection'],
            (string) Str::uuid().'.'.$extension,
            $disk,
        );

        $media = MediaFile::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()->id,
            'collection' => $validated['collection'],
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'scan_state' => 'pending',
            'metadata' => [
                'extension' => $extension,
                'visibility' => 'public',
            ],
        ]);

        return response()->json($media, 201);
    }
}
