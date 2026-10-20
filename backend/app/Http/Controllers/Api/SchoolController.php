<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListSchoolsRequest;
use App\Http\Requests\Api\StoreSchoolRequest;
use App\Http\Requests\Api\UpdateSchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Models\AuditLog;
use App\Models\School;
use App\Services\SchoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function __construct(
        private readonly SchoolService $schoolService,
    ) {}

    public function index(ListSchoolsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $filters['includeHidden'] = ($filters['includeHidden'] ?? false) && $request->user('sanctum')?->isAdmin();

        ['items' => $schools, 'total' => $total] = $this->schoolService->search($filters);

        // Body stays a plain array (existing clients); the total is a header.
        return response()->json(SchoolResource::collection($schools))
            ->header('X-Total-Count', (string) $total);
    }

    /** GET /schools/compare?ids=1,2,3 (2 to 4 schools). */
    public function compare(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'string', 'regex:/^\d+(,\d+)*$/']]);

        $ids = array_values(array_unique(array_map('intval', explode(',', $request->input('ids')))));

        if (count($ids) < 2 || count($ids) > 4) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['ids' => ['Compare between 2 and 4 schools.']],
            ], 422);
        }

        $result = $this->schoolService->compare($ids);

        return response()->json([
            'schools' => $result['schools']->map(fn ($school) => array_merge(
                (new SchoolResource($school))->resolve($request),
                [
                    'packageSummary' => $result['packages'][$school->id],
                    'reviewSummary' => $result['reviews'][$school->id],
                ],
            ))->values(),
            'badges' => $result['badges'],
            'missingIds' => array_values(array_diff($ids, $result['schools']->pluck('id')->all())),
        ]);
    }

    public function featured(): JsonResponse
    {
        $schools = $this->schoolService->featured();

        return response()->json(SchoolResource::collection($schools));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // Staff and admins see their own listing before it is live (DIQ-1101).
        $viewer = $request->user('sanctum');
        $staff = $viewer && ($viewer->isAdmin() || ($viewer->isSchool() && (int) $viewer->school_id === $id));
        $school = $this->schoolService->findById($id, includeHidden: $staff);

        if (! $school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        return response()->json(new SchoolResource($school));
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $school = $this->schoolService->findBySlug($slug);

        if (! $school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        return response()->json(new SchoolResource($school));
    }

    public function store(StoreSchoolRequest $request): JsonResponse
    {
        $school = $this->schoolService->create($request->toSnakeCase());

        return response()->json(new SchoolResource($school), 201);
    }

    public function update(UpdateSchoolRequest $request, int $id): JsonResponse
    {
        $school = School::find($id);

        if (! $school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $user = $request->user();
        if (! $user->isAdmin() && (int) $user->school_id !== $school->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $school = $this->schoolService->update($school, $request->toSnakeCase());

        return response()->json(new SchoolResource($school));
    }

    /** PATCH /admin/schools/{id}/listing-status (DIQ-1101): an admin takes a listing down or restores it. */
    public function updateListingStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:suspended,published'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $school = School::find($id);
        if (! $school) {
            return response()->json(['message' => 'School not found'], 404);
        }
        if ($school->listing_status === 'unclaimed') {
            return response()->json(['message' => 'Unclaimed listings go live when their owner claims them.'], 422);
        }

        $old = $school->listing_status;
        $school->forceFill(['listing_status' => $data['status']])->save();

        AuditLog::log('listing_status', 'School', $school->id, ['status' => $old],
            ['status' => $school->listing_status, 'reason' => $data['reason'] ?? null], $school->id);

        return response()->json(['listingStatus' => $school->listing_status]);
    }

    public function delete(int $id): JsonResponse
    {
        $school = School::find($id);

        if (! $school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $this->schoolService->delete($school);

        return response()->json(null, 204);
    }
}
