<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), ['schoolId' => ['nullable', 'integer']]);
        if ($validator->fails()) return response()->json(['error' => $validator->errors()->toJson()], 400);

        $query = Review::query()
            ->leftJoin('schools', 'reviews.school_id', '=', 'schools.id')
            ->select([
                'reviews.id',
                DB::raw('reviews.school_id as "schoolId"'),
                DB::raw('schools.name as "schoolName"'),
                DB::raw('reviews.author_name as "authorName"'),
                'reviews.rating',
                'reviews.content',
                'reviews.approved',
                DB::raw('reviews.created_at as "createdAt"'),
            ])
            ->orderByDesc('reviews.created_at');

        if (isset($validator->validated()['schoolId'])) {
            $query->where('reviews.school_id', $validator->validated()['schoolId']);
        }

        return response()->json($query->get()->map(fn ($r) => $this->normalizeRow($r))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schoolId' => ['required', 'integer'],
            'authorName' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string'],
            'approved' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) return response()->json(['error' => $validator->errors()->toJson()], 400);

        $review = Review::create([
            'school_id' => $validator->validated()['schoolId'],
            'author_name' => $validator->validated()['authorName'],
            'rating' => $validator->validated()['rating'],
            'content' => $validator->validated()['content'],
            'approved' => $validator->validated()['approved'] ?? false,
        ]);

        return response()->json($this->normalizeModel($review), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $review = Review::query()->find($id);
        if (!$review) return response()->json(['error' => 'Review not found'], 404);

        $validator = Validator::make($request->all(), [
            'authorName' => ['sometimes', 'string', 'max:255'],
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'content' => ['sometimes', 'string'],
            'approved' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) return response()->json(['error' => 'Invalid input'], 400);

        $map = ['authorName' => 'author_name', 'rating' => 'rating', 'content' => 'content', 'approved' => 'approved'];
        foreach ($validator->validated() as $k => $v) $review->{$map[$k]} = $v;
        $review->save();

        return response()->json($this->normalizeModel($review));
    }

    public function delete(int $id): JsonResponse
    {
        Review::query()->where('id', $id)->delete();
        return response()->json(null, 204);
    }

    private function normalizeRow(object $r): array
    {
        $data = (array) $r;
        $data['createdAt'] = optional($r->createdAt)->toISOString() ?? (string) $r->createdAt;
        return $data;
    }

    private function normalizeModel(Review $r): array
    {
        return [
            'id' => $r->id,
            'schoolId' => $r->school_id,
            'authorName' => $r->author_name,
            'rating' => (int) $r->rating,
            'content' => $r->content,
            'approved' => (bool) $r->approved,
            'createdAt' => $r->created_at?->toISOString(),
        ];
    }
}
