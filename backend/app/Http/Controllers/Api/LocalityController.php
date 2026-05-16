<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Locality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LocalityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Locality::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:localities,slug'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->toJson()], 400);
        }

        $locality = Locality::create($validator->validated());
        return response()->json($locality, 201);
    }

    public function show(int $id): JsonResponse
    {
        $locality = Locality::query()->find($id);
        if (!$locality) {
            return response()->json(['error' => 'Locality not found'], 404);
        }

        return response()->json($locality);
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $locality = Locality::query()->where('slug', $slug)->first();
        if (!$locality) {
            return response()->json(['error' => 'Locality not found'], 404);
        }

        return response()->json($locality);
    }
}
