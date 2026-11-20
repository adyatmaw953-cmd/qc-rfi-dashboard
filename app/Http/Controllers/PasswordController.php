<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Show the change password form.
     */
    public function edit(Request $request): View
    {
        return view('modules.profile.password', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
