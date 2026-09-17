<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolSettingsController extends Controller
{
    public function show(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authorizeSchool($request, $schoolId)) {
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
        if ($deny = $this->authorizeSchool($request, $schoolId)) {
            return $deny;
        }

        $data = $request->validate([
            'settings' => ['required', 'array'],
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

    private function authorizeSchool(Request $request, int $schoolId): ?JsonResponse
    {
        if (! School::find($schoolId)) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $user = $request->user();
        if ($user->isAdmin()) {
            return null;
        }

        if ((int) $user->school_id !== $schoolId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }
}
