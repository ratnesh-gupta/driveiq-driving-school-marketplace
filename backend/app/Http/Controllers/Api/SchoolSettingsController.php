<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SchoolSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolSettingsController extends Controller
{
    public function show(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        $row = SchoolSetting::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->first();

        $settings = array_replace_recursive(
            SchoolSetting::defaults(),
            $row?->settings ?? []
        );

        return response()->json([
            'schoolId' => $schoolId,
            'settings' => $settings,
        ]);
    }

    public function update(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        // Only known keys, with typed values (DIQ-707): this JSON drives who gets
        // notified and when, so arbitrary input is rejected.
        $data = $request->validate([
            'settings' => ['required', 'array:notifications,timezone,locale,lead_auto_assign'],
            'settings.notifications' => ['sometimes', 'array:email,sms,in_app,new_inquiry,new_review,reminder_after_minutes'],
            'settings.notifications.email' => ['sometimes', 'boolean'],
            'settings.notifications.sms' => ['sometimes', 'boolean'],
            'settings.notifications.in_app' => ['sometimes', 'boolean'],
            'settings.notifications.new_inquiry' => ['sometimes', 'boolean'],
            'settings.notifications.new_review' => ['sometimes', 'boolean'],
            'settings.notifications.reminder_after_minutes' => ['sometimes', 'integer', 'in:0,30,60,120,240'],
            'settings.timezone' => ['sometimes', 'timezone'],
            'settings.locale' => ['sometimes', 'string', 'in:en-IN,hi-IN,mr-IN'],
            'settings.lead_auto_assign' => ['sometimes', 'boolean'],
        ]);

        $row = SchoolSetting::withoutGlobalScope('school')
            ->firstOrNew(['school_id' => $schoolId]);

        $old = $row->settings ?? [];
        $merged = array_replace_recursive(SchoolSetting::defaults(), $old, $data['settings']);
        $row->settings = $merged;
        $row->save();

        AuditLog::log('update', 'SchoolSetting', $row->id, $old, $merged);

        return response()->json([
            'schoolId' => $schoolId,
            'settings' => $merged,
        ]);
    }
}
