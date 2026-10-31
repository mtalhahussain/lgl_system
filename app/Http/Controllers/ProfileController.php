<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['person' => $request->user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500', 'emergency_contact' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8|confirmed', 'current_password' => 'nullable|required_with:password|current_password']);
        unset($data['current_password']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        \Illuminate\Support\Facades\DB::transaction(fn () => $request->user()->update($data));

        return redirect()->route('profile.edit')->with('success', 'Profile updated.');
    }
}
