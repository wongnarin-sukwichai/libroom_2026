<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;

class AdminMemberController extends Controller
{
    public function index(Request $request)
    {
        $query = Member::select('id', 'name', 'email', 'code', 'faculty', 'branch', 'type', 'created_at')
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

        $paginated = $query->orderByDesc('created_at')->paginate(15);

        return response()->json($paginated);
    }
}
