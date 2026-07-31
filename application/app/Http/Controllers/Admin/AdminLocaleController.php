<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(['nb', 'ru'])],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $request->user()->forceFill(['locale' => $validated['locale']])->save();

        $redirectTo = $validated['redirect_to'] ?? '';

        if (! str_starts_with($redirectTo, '/admin') || str_starts_with($redirectTo, '//')) {
            $redirectTo = route('admin.dashboard');
        }

        return redirect()->to($redirectTo);
    }
}
