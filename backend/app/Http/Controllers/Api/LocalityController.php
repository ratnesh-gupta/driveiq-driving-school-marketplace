<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLocalityRequest;
use App\Http\Resources\LocalityResource;
use App\Models\Locality;
use Illuminate\Http\JsonResponse;

class LocalityController extends Controller
{
    public function index(): JsonResponse
    {
        $localities = Locality::query()->orderBy('name')->get();

        return response()->json(LocalityResource::collection($localities));
    }

    public function store(StoreLocalityRequest $request): JsonResponse
    {
        $locality = Locality::create($request->validated());

        return response()->json(new LocalityResource($locality), 201);
    }

    public function show(int $id): JsonResponse
    {
        $locality = Locality::find($id);

        if (! $locality) {
            return response()->json(['message' => 'Locality not found'], 404);
        }

        return response()->json(new LocalityResource($locality));
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $locality = Locality::where('slug', $slug)->first();

        if (! $locality) {
            return response()->json(['message' => 'Locality not found'], 404);
        }

        return response()->json(new LocalityResource($locality));
    }
}
