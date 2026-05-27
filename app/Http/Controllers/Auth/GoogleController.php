<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $admin = User::where('email', $googleUser->getEmail())->first();
        if ($admin) {
            $admin->update([
                'google_id' => $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar(),
            ]);
            Auth::guard('admin')->login($admin);
            return redirect()->route('admin.dashboard');
        }

        $member = Member::updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'google_id' => $googleUser->getId(),
                'name'      => $googleUser->getName(),
                'avatar'    => $googleUser->getAvatar(),
            ]
        );

        Auth::login($member);
        return redirect()->route('welcome');
    }

    public function logout()
    {
        Auth::guard('admin')->logout();
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('welcome');
    }
}
