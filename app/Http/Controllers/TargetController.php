<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Models\Visa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TargetController extends Controller
{
    //


public function index(Request $request)
{
    $query = Target::with('user');

    // Optional filters
    if ($request->user_id) {
        $query->where('user_id', $request->user_id);
    }

    if ($request->year) {
        $query->where('year', $request->year);
    }

    if ($request->month) {
        $query->where('month', $request->month);
    }

    $targets = $query->orderBy('id', 'desc')->paginate(10);

    // 🔥 modify response data
    $targets->getCollection()->transform(function ($t) {
        $t->remaining = $t->target - $t->achieved;

        // optional progress %
        $t->progress = $t->target > 0
            ? round(($t->achieved / $t->target) * 100, 2)
            : 0;

        return $t;
    });

    return response()->json([
        'status' => true,
        'data' => $targets
    ]);
}


public function store(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'target'  => 'required|integer|min:1',
        'year'    => 'required|integer',
        'month'   => 'required|integer|min:1|max:12',
    ]);

    // 🔥 check if already exists
    $exists = Target::where('user_id', $request->user_id)
        ->where('year', $request->year)
        ->where('month', $request->month)
        ->exists();

    if ($exists) {
        return response()->json([
            'status' => false,
            'message' => 'Target already set for this user in this month'
        ], 409);
    }

    // create only (no update allowed)
    $target = Target::create([
        'user_id' => $request->user_id,
        'target'  => $request->target,
        'year'    => $request->year,
        'month'   => $request->month,
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Target set successfully',
        'data' => $target
    ]);
}


public function update(Request $request, $id)
{
    $request->validate([
        'target'  => 'required|integer|min:1',
        'year'    => 'required|integer',
        'month'   => 'required|integer|min:1|max:12',
    ]);

    $target = Target::find($id);

    if (!$target) {
        return response()->json([
            'status' => false,
            'message' => 'Target not found'
        ], 404);
    }

    // 🔥 Optional: prevent duplicate (same user, year, month)
    $exists = Target::where('user_id', $target->user_id)
        ->where('year', $request->year)
        ->where('month', $request->month)
        ->where('id', '!=', $id)
        ->exists();

    if ($exists) {
        return response()->json([
            'status' => false,
            'message' => 'Another target already exists for this user in this month'
        ], 409);
    }

    $target->update([
        'target' => $request->target,
        'year'   => $request->year,
        'month'  => $request->month,
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Target updated successfully',
        'data' => $target
    ]);
}


public function monthlyAchieved(Request $request)
{
    $year = $request->year ?? date('Y');

    $query = Target::query();

    // 🔐 role check
    if (auth()->check() && auth()->user()->role !== 'admin') {
        $query->where('user_id', auth()->id());
    }

    // 🔹 get monthly data (target + achieved)
    $results = $query
        ->where('year', $year)
        ->selectRaw('month, SUM(target) as total_target, SUM(achieved) as total_achieved')
        ->groupBy('month')
        ->get()
        ->keyBy('month');

    // 🔹 build full 12 months
    $months = [];

    for ($m = 1; $m <= 12; $m++) {

        $target = (int) ($results[$m]->total_target ?? 0);
        $achieved = (int) ($results[$m]->total_achieved ?? 0);

        $months[] = [
            'month' => $m,
            'target' => $target,
            'achieved' => $achieved,
            'remaining' => max($target - $achieved, 0), // negative avoid
        ];
    }

    return response()->json([
        'status' => true,
        'year' => $year,
        'data' => $months
    ]);
}


public function topUsersByAchieved(Request $request)
{
    $year  = $request->year ?? date('Y');
    $limit = 5; // ✅ always top 5

    $monthsData = [];

    for ($month = 1; $month <= 12; $month++) {

        $topUsers = Target::query()
            ->with('user')
            ->where('year', $year)
            ->where('month', $month)
            ->select('user_id', DB::raw('SUM(achieved) as total_achieved'))
            ->groupBy('user_id')
            ->orderByDesc('total_achieved')
            ->limit($limit)
            ->get();

        $formatted = $topUsers->values()->map(function ($item, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => $item->user_id,
                'name' => $item->user->name ?? 'Unknown',
                'achieved' => (int) $item->total_achieved,
            ];
        });

        $monthsData[] = [
            'month' => $month,
            'top_users' => $formatted
        ];
    }

    return response()->json([
        'status' => true,
        'year' => $year,
        'data' => $monthsData
    ]);
}


public function achievedSummary(Request $request)
{
    $date = $request->date ?? date('Y-m-d');
    $year = date('Y', strtotime($date));
    $month = date('m', strtotime($date));

    $query = Target::query();

    // 🔐 role check
    if (auth()->check() && auth()->user()->role !== 'admin') {
        $query->where('user_id', auth()->id());
    }

    // 🔹 Today achieved (safe range based on created_at)
    $todayStart = $date . ' 00:00:00';
    $todayEnd   = $date . ' 23:59:59';

    $todayAchieved = (clone $query)
        ->whereBetween('created_at', [$todayStart, $todayEnd])
        ->sum('achieved');

    // 🔹 Monthly achieved
    $monthlyAchieved = (clone $query)
        ->whereYear('created_at', $year)
        ->whereMonth('created_at', $month)
        ->sum('achieved');

    // 🔹 Total achieved
    $totalAchieved = (clone $query)->sum('achieved');

    return response()->json([
        'status' => true,
        'data' => [
            'date' => $date,
            'today_achieved' => (int) $todayAchieved,
            'monthly_achieved' => (int) $monthlyAchieved,
            'total_achieved' => (int) $totalAchieved,
        ]
    ]);
}


public function monthlySummary(Request $request)
{
    $year  = $request->year ?? date('Y');
    $month = $request->month ?? date('m');

    $query = Target::query();

    // 🔐 role check
    if (auth()->check() && auth()->user()->role !== 'admin') {
        $query->where('user_id', auth()->id());
    }

    // 🔹 filter by year & month
    $query->where('year', $year)
          ->where('month', $month);

    // 🔹 totals
    $totalTarget = (clone $query)->sum('target');
    $totalAchieved = (clone $query)->sum('achieved');

    // 🔹 remaining
    $totalRemaining = $totalTarget - $totalAchieved;

    // 🔹 progress %
    $progress = $totalTarget > 0
        ? round(($totalAchieved / $totalTarget) * 100, 2)
        : 0;

    return response()->json([
        'status' => true,
        'data' => [
            'year' => (int) $year,
            'month' => (int) $month,
            'total_target' => (int) $totalTarget,
            'total_achieved' => (int) $totalAchieved,
            'total_remaining' => (int) $totalRemaining,
            'progress' => $progress
        ]
    ]);
}

}
