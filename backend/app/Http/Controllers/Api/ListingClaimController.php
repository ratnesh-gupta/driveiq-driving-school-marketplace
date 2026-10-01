<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListingClaim;
use App\Models\Prospect;
use App\Models\School;
use App\Services\ListingClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** DIQ-1104: claiming a pre-built listing from a link. Public; the token is the key. */
class ListingClaimController extends Controller
{
    public function __construct(private readonly ListingClaimService $claims) {}

    public function show(string $token): JsonResponse
    {
        $claim = ListingClaim::findByToken($token);
        if (! $claim) {
            return response()->json(['message' => 'This claim link is not valid.'], 404);
        }
        if (! $claim->isUsable()) {
            return response()->json(['message' => 'This listing has already been claimed or the link has expired.'], 410);
        }
        $claim->opened_at ??= now();
        $claim->save();

        $school = $claim->school->load('locality:id,name');

        return response()->json([
            'listing' => [
                'name' => $school->name,
                'type' => $school->listing_type,
                'locality' => $school->locality?->name,
                'address' => $school->address,
            ],
            'channels' => $this->claims->channels($school),
            'expiresAt' => $claim->expires_at->toISOString(),
        ]);
    }

    public function sendCode(Request $request, string $token): JsonResponse
    {
        $claim = $this->usable($token);
        $data = $request->validate(['channel' => ['required', 'in:email,sms']]);

        $code = $this->claims->sendCode($claim, $data['channel']);

        return response()->json(['sentTo' => $code->sent_to_masked, 'expiresAt' => $code->expires_at->toISOString()]);
    }

    public function complete(Request $request, string $token): JsonResponse
    {
        $claim = $this->usable($token);
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
        ]);

        ['user' => $user, 'token' => $apiToken, 'school' => $school] = $this->claims->complete($claim, $data['code'], $data);

        return response()->json([
            'user' => $user,
            'token' => $apiToken,
            'schoolId' => $school->id,
            'schoolRole' => 'owner',
            'listingStatus' => $school->listing_status,
        ], 201);
    }

    public function decline(string $token): JsonResponse
    {
        $this->claims->decline($this->usable($token));

        return response()->json(['message' => 'Done. We have removed the listing and will not contact you again.']);
    }

    /** POST /admin/prospects/{id}/claim-link: a link the team can share by phone or WhatsApp. */
    public function adminLink(int $id): JsonResponse
    {
        $prospect = Prospect::findOrFail($id);
        $school = $prospect->school_id ? School::find($prospect->school_id) : null;
        if (! $school || $school->listing_status !== 'unclaimed') {
            return response()->json(['message' => 'Create the listing first; a claimed listing needs no link.'], 422);
        }

        ['claim' => $claim, 'url' => $url] = $this->claims->issue($school, $prospect);

        return response()->json(['url' => $url, 'expiresAt' => $claim->expires_at->toISOString()], 201);
    }

    private function usable(string $token): ListingClaim
    {
        $claim = ListingClaim::findByToken($token);
        abort_if(! $claim, 404, 'This claim link is not valid.');
        abort_if(! $claim->isUsable(), 410, 'This listing has already been claimed or the link has expired.');

        return $claim;
    }
}
