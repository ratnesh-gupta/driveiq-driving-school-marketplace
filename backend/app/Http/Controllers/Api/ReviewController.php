<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReviewRequest;
use App\Http\Requests\Api\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\AuditLog;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'schoolId' => ['nullable', 'integer'],
        ]);

        // Public marketplace listing must not be limited to the caller's school.
        $query = Review::withoutGlobalScope('school')
            ->with('school')
            ->orderByDesc('created_at');

        if ($request->filled('schoolId')) {
            $query->where('school_id', $request->input('schoolId'));
        }

        return response()->json(ReviewResource::collection($query->get()));
    }

    public function store(StoreReviewRequest $request): JsonResponse
    {
        $data = $request->toSnakeCase();
        $data['approved'] ??= false;

        $review = Review::withoutGlobalScope('school')->create($data);
        $review->load('school');

        return response()->json(new ReviewResource($review), 201);
    }

    public function update(UpdateReviewRequest $request, int $id): JsonResponse
    {
        $review = Review::find($id);

        if (! $review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $oldValues = $review->only(['approved', 'rating', 'content']);
        $review->fill($request->toSnakeCase());
        $review->save();
        $review->load('school');

        AuditLog::log('update', 'Review', $review->id, $oldValues, $review->only(['approved', 'rating', 'content']));

        return response()->json(new ReviewResource($review));
    }

    public function delete(int $id): JsonResponse
    {
        $review = Review::find($id);

        if (! $review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        AuditLog::log('delete', 'Review', $review->id, $review->toArray(), []);
        $review->delete();

        return response()->json(null, 204);
    }
}
