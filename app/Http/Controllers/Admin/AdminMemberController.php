<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminMemberController extends Controller
{
    public function index(Request $request)
    {
        $query = Member::select('id', 'name', 'email', 'code', 'faculty', 'branch', 'type', 'blocked_at', 'block_reason', 'created_at')
            ->selectSub(
                \App\Models\BookingGroup::selectRaw('COUNT(*)')
                    ->whereColumn('lead_user_id', 'members.id'),
                'total_bookings'
            )
            ->selectSub(
                \App\Models\BookingGroup::select('date')
                    ->whereColumn('lead_user_id', 'members.id')
                    ->orderByDesc('date')
                    ->limit(1),
                'last_booking'
            );

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('name',  'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('code',  'like', "%{$s}%")
            );
        }

        if ($request->input('filter') === 'blocked') {
            $query->whereNotNull('blocked_at');
        }

        $paginated = $query->orderByDesc('created_at')->paginate(15);

        return response()->json([
            ...$paginated->toArray(),
            'all_count'     => Member::count(),
            'blocked_count' => Member::whereNotNull('blocked_at')->count(),
        ]);
    }

    public function updateCode(Request $request, Member $member)
    {
        $request->validate([
            'code' => ['nullable', 'string', Rule::unique('members', 'code')->ignore($member->id)],
        ]);

        $member->update(['code' => $request->code ?: null]);
        return response()->json(['ok' => true, 'code' => $member->code]);
    }

    public function block(Request $request, Member $member)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $member->update([
            'blocked_at'   => now(),
            'block_reason' => $data['reason'],
            'blocked_by'   => $request->user('admin')->id,
        ]);

        return response()->json([
            'ok'           => true,
            'blocked_at'   => $member->blocked_at,
            'block_reason' => $member->block_reason,
        ]);
    }

    public function unblock(Member $member)
    {
        $member->update(['blocked_at' => null, 'block_reason' => null, 'blocked_by' => null]);

        return response()->json(['ok' => true]);
    }
}
