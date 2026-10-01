<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SchoolResource;
use App\Models\GoogleBusinessConnection;
use App\Models\ListingClaim;
use App\Models\School;
use App\Models\User;
use App\Services\GoogleBusinessService;
use App\Services\ListingClaimService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * DIQ-1107: connect a Google Business Profile to a listing (owner) and use
 * it as proof of ownership when claiming a pre-built listing.
 */
class GoogleBusinessController extends Controller
{
    public function __construct(private readonly GoogleBusinessService $google) {}

    public function status(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->access()->school($request, $id)) {
            return $deny;
        }
        $c = GoogleBusinessConnection::where('school_id', $id)->first();

        return response()->json([
            'enabled' => $this->google->enabled(),
            'connected' => (bool) $c,
            'location' => $c?->location_name ? ['title' => $c->location_title, 'placeId' => $c->place_id] : null,
            'rating' => $c?->rating,
            'reviewCount' => $c?->review_count,
            'importedAt' => $c?->imported_at?->toISOString(),
        ]);
    }

    public function connect(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->access()->school($request, $id, ownerOnly: true)) {
            return $deny;
        }
        abort_unless($this->google->enabled(), 404, 'Google Business Profile is not set up on this server.');

        return response()->json(['url' => $this->google->authUrl(['purpose' => 'owner', 'schoolId' => $id, 'userId' => $request->user()->id])]);
    }

    public function locations(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->access()->school($request, $id, ownerOnly: true)) {
            return $deny;
        }
        $c = GoogleBusinessConnection::where('school_id', $id)->firstOrFail();

        try {
            return response()->json($this->google->locations($this->google->accessToken($c)));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RequestException) {
            return response()->json(['message' => 'Google did not answer. If this keeps happening, check the Business Profile API access for this project.'], 502);
        }
    }

    public function import(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->access()->school($request, $id, ownerOnly: true)) {
            return $deny;
        }
        $data = $request->validate(['location' => ['required', 'string', 'max:255']]);
        $c = GoogleBusinessConnection::where('school_id', $id)->firstOrFail();

        try {
            $token = $this->google->accessToken($c);
            $location = collect($this->google->locations($token))->firstWhere('name', $data['location']);
            if (! $location) {
                return response()->json(['message' => 'That location is not in your Google account.'], 422);
            }
            $school = $this->google->import(School::findOrFail($id), $c, $location);
            $summary = $this->google->reviewSummary($token, $location['name']);
            $c->forceFill(['rating' => $summary['rating'], 'review_count' => $summary['reviewCount']])->save();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RequestException) {
            return response()->json(['message' => 'Google did not answer. Try again in a minute.'], 502);
        }

        return response()->json([
            'school' => new SchoolResource($school->load('locality')),
            'verified' => (bool) $location['verified'],
            'rating' => $summary['rating'],
            'reviewCount' => $summary['reviewCount'],
        ]);
    }

    public function disconnect(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->access()->school($request, $id, ownerOnly: true)) {
            return $deny;
        }
        if ($c = GoogleBusinessConnection::where('school_id', $id)->first()) {
            $this->google->revoke($c);
        }

        return response()->json(null, 204);
    }

    /** GET /claims/{token}/google: prove ownership of a pre-built listing with Google. */
    public function claimConnect(string $token): JsonResponse
    {
        $claim = ListingClaim::findByToken($token);
        abort_if(! $claim || ! $claim->isUsable(), 410, 'This listing has already been claimed or the link has expired.');
        abort_unless($this->google->enabled() && $claim->school->google_place_id, 404, 'Google sign-in is not available for this listing.');

        return response()->json(['url' => $this->google->authUrl(['purpose' => 'claim', 'claimToken' => $token])]);
    }

    /** GET /google-business/callback: where Google sends the browser back. */
    public function callback(Request $request): RedirectResponse
    {
        $frontend = config('app.frontend_url');
        $state = $this->google->readState((string) $request->query('state'));
        if (! $state) {
            return redirect()->away($frontend.'/dashboard/profile?google=expired');
        }
        $back = $state['purpose'] === 'claim'
            ? $frontend.'/claim/'.rawurlencode($state['claimToken'])
            : $frontend.'/dashboard/profile';

        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->away($back.'?google=cancelled');
        }

        try {
            $tokens = $this->google->exchangeCode((string) $request->query('code'));
        } catch (RuntimeException) {
            return redirect()->away($back.'?google=failed');
        }

        return $state['purpose'] === 'claim'
            ? $this->finishClaim($state['claimToken'], $tokens['access_token'], $back)
            : $this->finishConnect($state, $tokens, $back);
    }

    private function finishConnect(array $state, array $tokens, string $back): RedirectResponse
    {
        $user = User::find($state['userId']);
        // The owner may have lost the listing between leaving and coming back.
        if (! $user || (int) $user->school_id !== (int) $state['schoolId'] || ! $this->access()->isOwner($user, (int) $state['schoolId'])) {
            return redirect()->away($back.'?google=failed');
        }

        GoogleBusinessConnection::updateOrCreate(['school_id' => $state['schoolId']], [
            'user_id' => $user->id,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_expires_at' => now()->addSeconds($tokens['expires_in']),
        ]);

        return redirect()->away($back.'?google=connected');
    }

    /**
     * The claimant manages the verified Google location this listing was
     * built from: issue a one-time code the claim page uses to finish.
     */
    private function finishClaim(string $claimToken, string $accessToken, string $back): RedirectResponse
    {
        $claim = ListingClaim::findByToken($claimToken);
        if (! $claim || ! $claim->isUsable()) {
            return redirect()->away($back);
        }

        try {
            $match = collect($this->google->locations($accessToken))
                ->first(fn ($l) => $l['verified'] && $l['placeId'] === $claim->school->google_place_id);
        } catch (\Throwable) {
            $match = null;
        }
        if (! $match) {
            return redirect()->away($back.'?google=nomatch');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $claim->codes()->create([
            'channel' => 'google',
            'sent_to_masked' => 'Google Business Profile',
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(ListingClaimService::CODE_MINUTES),
        ]);

        return redirect()->away($back.'?google=verified&code='.$code);
    }
}
