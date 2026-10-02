<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update the user's community contributor privacy preference.
     */
    public function updateContributorPrivacy(Request $request): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'censor_contributor_name' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->censor_contributor_name = (bool) $validated['censor_contributor_name'];
        $user->save();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'censor_contributor_name' => (bool) $user->censor_contributor_name,
                'message' => 'Contributor privacy preference updated.',
            ]);
        }

        return Redirect::back()->with('status', 'contributor-privacy-updated');
    }

    /**
     * Update the user's avatar.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar_data' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ]);

        $user = $request->user();

        if ($request->filled('avatar_data')) {
            $data = $request->input('avatar_data');
            if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
                $data = substr($data, strpos($data, ',') + 1);
                $type = strtolower($type[1]);
                if (!in_array($type, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    return back()->withErrors(['avatar' => 'Invalid image format.']);
                }
                $data = base64_decode($data);
                if ($data === false) {
                    return back()->withErrors(['avatar' => 'Image decode failed.']);
                }
            } else {
                return back()->withErrors(['avatar' => 'Invalid image payload.']);
            }

            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $filename = 'avatars/' . $user->id . '_' . time() . '_' . Str::random(8) . '.' . ($type === 'jpeg' ? 'jpg' : $type);
            Storage::disk('public')->put($filename, $data);

            $user->avatar_path = $filename;
            $user->save();
        } elseif ($request->hasFile('avatar')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_path = $path;
            $user->save();
        }

        return Redirect::route('profile.edit')->with('status', 'avatar-updated');
    }

    /**
     * Delete the user's avatar.
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->avatar_path = null;
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'avatar-deleted');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if (strcasecmp(trim($user->email), 'admin@bangued.ph') === 0) {
            return Redirect::route('profile.edit')->withErrors(['userDeletion' => 'The root administrator account (admin@bangued.ph) cannot be deleted.']);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
