<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReviewRequest;
use App\Http\Requests\Api\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
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

        $query = Review::with('school')
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

        $review = Review::create($data);
        $review->load('school');

        return response()->json(new ReviewResource($review), 201);
    }

    public function update(UpdateReviewRequest $request, int $id): JsonResponse
    {
        $review = Review::find($id);

        if (! $review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $review->fill($request->toSnakeCase());
        $review->save();
        $review->load('school');

        return response()->json(new ReviewResource($review));
    }

    public function delete(int $id): JsonResponse
    {
        $review = Review::find($id);

        if (! $review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $review->delete();

        return response()->json(null, 204);
    }
}
