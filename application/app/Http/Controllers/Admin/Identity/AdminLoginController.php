<?php

namespace App\Http\Controllers\Admin\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Identity\AdminLoginRequest;
use App\Modules\Identity\Actions\AuthenticateAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.identity.login');
    }

    public function store(AdminLoginRequest $request, AuthenticateAdmin $authenticateAdmin): RedirectResponse
    {
        $user = $authenticateAdmin->handle($request->normalizedEmail(), $request->string('password')->toString());

        if ($user === null) {
            return back()
                ->withInput($request->safe()->except('password'))
                ->withErrors(['email' => __('admin.login.invalid_credentials')]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }
}
