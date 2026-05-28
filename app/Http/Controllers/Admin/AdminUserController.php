<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $paginated = User::orderBy('created_at')
            ->paginate(10, ['id', 'name', 'email', 'role', 'created_at'], 'page', $request->page);

        $data = $paginated->toArray();
        $data['admin_count'] = User::where('role', 'admin')->count();
        $data['staff_count'] = User::where('role', 'staff')->count();

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|unique:users',
            'role'  => 'required|in:admin,staff',
        ]);

        $user = User::create([
            'google_id' => 'pending_' . Str::uuid(),
            'name'      => '',
            'email'     => $data['email'],
            'role'      => $data['role'],
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role'  => 'required|in:admin,staff',
        ]);

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user('admin')->id) {
            return response()->json(['message' => 'ไม่สามารถลบบัญชีของตัวเองได้'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'ลบเรียบร้อย']);
    }
}
