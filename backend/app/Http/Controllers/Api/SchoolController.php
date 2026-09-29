<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListSchoolsRequest;
use App\Http\Requests\Api\StoreSchoolRequest;
use App\Http\Requests\Api\UpdateSchoolRequest;
use App\Http\Resources\SchoolResource;
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
        ['items' => $schools, 'total' => $total] = $this->schoolService->search($request->validated());

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

    public function show(int $id): JsonResponse
    {
        $school = $this->schoolService->findById($id);

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
