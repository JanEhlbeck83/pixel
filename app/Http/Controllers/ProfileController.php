<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Activity;
use App\Models\Order;
use App\Services\Payments\Shop;
use App\Services\Wall\PixelQuota;
use Throwable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, Shop $shop): View
    {
        $user = $request->user();

        $user->orders()->where('status', Order::PENDING)->where('provider', '!=', 'fake')
            ->where('created_at', '>', now()->subDays(2))->latest()->limit(5)->get()
            ->each(function (Order $order) use ($shop) {
                try {
                    $shop->refresh($order);
                } catch (Throwable $e) {
                    report($e);
                }
            });
        $communityMembership = $user->communityMembership()->with('group')->first();

        return view('profile.edit', [
            'user' => $user->fresh(),
            'orders' => $user->orders()->latest()->limit(50)->get(),
            'quota' => app(PixelQuota::class)->status($request),
            'regenSeconds' => app(PixelQuota::class)->regenSeconds(),
            'nextFreePixelIn' => app(PixelQuota::class)->secondsUntilNext($user),
            'communityMembership' => $communityMembership,
            'activeGroupMembersCount' => $communityMembership
                ? $communityMembership->group->members()->where('last_active_at', '>=', now()->subMinutes(5))->count()
                : 0,
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

        if ($request->has('newsletter')) {
            $wants = $request->boolean('newsletter');
            $current = $request->user()->newsletter_subscribed_at;
            $request->user()->newsletter_subscribed_at = $wants ? ($current ?? now()) : null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function updateProjectNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_phase_notifications' => ['required', 'boolean'],
        ]);
        $request->user()->forceFill($data)->save();

        return Redirect::route('profile.edit')->with('status', 'project-notifications-updated');
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

        // Der Name steht im Klartext in der Beschreibung und muss mit dem Konto verschwinden.
        Activity::where('user_id', $user->id)->update(['type' => 'user_deleted', 'params' => null, 'description' => 'Ein gelöschter Nutzer hat Pixel gesetzt.']);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
