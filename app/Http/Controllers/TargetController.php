<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Models\Visa;
use Carbon\Carbon;
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
    // 🔹 রিকোয়েস্ট থেকে তারিখ নেওয়া, না থাকলে বর্তমান সময় (BD Timezone)
    $dateInput = $request->date
        ? Carbon::parse($request->date, 'Asia/Dhaka')
        : Carbon::now('Asia/Dhaka');

    $targetDate = $dateInput->toDateString(); // Y-m-d ফরম্যাট (যেমন: "2026-05-23")
    $year = $dateInput->year;
    $month = $dateInput->month;

    $query = Target::query();

    // 🔐 রোল চেক
    if (auth()->check() && auth()->user()->role !== 'admin') {
        $query->where('user_id', auth()->id());
    }

    // 🔹 ১. Today achieved (সরাসরি BD টাইমজোনে তারিখ ম্যাচ করা)
    // ডাটাবেজের UTC সময়কে Asia/Dhaka তে রূপান্তর করে আজকের তারিখের সাথে মেলানো হচ্ছে
    $todayAchieved = (clone $query)
        ->whereRaw("DATE(CONVERT_TZ(created_at, '+00:00', '+06:00')) = ?", [$targetDate])
        ->sum('achieved');

    // 🔹 ২. Monthly achieved (সরাসরি মাস এবং বছর ফিল্টার)
    $monthlyAchieved = (clone $query)
        ->whereRaw("YEAR(CONVERT_TZ(created_at, '+00:00', '+06:00')) = ?", [$year])
        ->whereRaw("MONTH(CONVERT_TZ(created_at, '+00:00', '+06:00')) = ?", [$month])
        ->sum('achieved');

    // 🔹 ৩. Total achieved
    $totalAchieved = (clone $query)->sum('achieved');

    return response()->json([
        'status' => true,
        'data' => [
            'date'             => $targetDate,
            'today_achieved'   => (int) $todayAchieved,
            'monthly_achieved' => (int) $monthlyAchieved,
            'total_achieved'   => (int) $totalAchieved,
        ]
    ]);
}


    public function monthlySummary(Request $request)
    {
        $year  = $request->year ?? date('Y');
        $month = $request->month ?? date('m');

        $user = auth()->user();

        $targetQuery = Target::where('year', $year)
            ->where('month', $month);

        // 🔐 Role-based filter
        if ($user->role !== 'admin') {
            $targetQuery->where('user_id', $user->id);
        } else {
            if ($request->user_id) {
                $targetQuery->where('user_id', $request->user_id);
            }
        }

        $targets = $targetQuery->get();

        // 🔹 Total Target
        $totalTarget = $targets->sum('target');

        // 🔹 Total Achieved (stored in target table)
        $totalAchieved = $targets->sum('achieved');

        // 🔹 Remaining
        $totalRemaining = $totalTarget - $totalAchieved;

        // 🔹 Progress
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
