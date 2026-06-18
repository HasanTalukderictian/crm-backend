<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Models\Visa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TargetController extends Controller
{
    public function index(Request $request)
    {
        $query = Target::with('user');

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

        $targets->getCollection()->transform(function ($t) {
            $t->remaining = $t->target - $t->achieved;
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

        $target = Target::create([
            'user_id' => $request->user_id,
            'target'  => $request->target,
            'year'    => $request->year,
            'month'   => $request->month,
        ]);

        // 🟢 Send email notification to the user
        $this->sendTargetEmail($target->user_id, $target, 'created');

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

        // 🟢 Send email notification to the user
        $this->sendTargetEmail($target->user_id, $target, 'updated');

        return response()->json([
            'status' => true,
            'message' => 'Target updated successfully',
            'data' => $target
        ]);
    }

    /**
     * 📧 Send target notification email to user
     */
    private function sendTargetEmail($userId, $target, $action = 'created')
    {
        try {
            $user = User::find($userId);

            if (!$user || !$user->email) {
                return; // Skip if user has no email
            }

            $monthName = date('F', mktime(0, 0, 0, $target->month, 1));

            $subject = $action === 'created'
                ? "🎯 New Target Assigned for {$monthName} {$target->year}"
                : "🎯 Target Updated for {$monthName} {$target->year}";

            $data = [
                'user' => $user,
                'target' => $target,
                'monthName' => $monthName,
                'action' => $action,
                'targetAmount' => number_format($target->target, 0),
                'year' => $target->year,
                'month' => $target->month,
            ];

            Mail::send('emails.target_notification', $data, function ($message) use ($user, $subject) {
                $message->to($user->email, $user->name)
                        ->subject($subject)
                        ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            });

            Log::info("Target email sent to: {$user->email} for month {$target->month}/{$target->year}");
        } catch (\Exception $e) {

            Log::error("Failed to send target email: " . $e->getMessage());
        }
    }

    public function monthlyAchieved(Request $request)
    {
        $year = $request->year ?? date('Y');

        $query = Target::query();

        if (auth()->check() && auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id());
        }

        $results = $query
            ->where('year', $year)
            ->selectRaw('month, SUM(target) as total_target, SUM(achieved) as total_achieved')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $months = [];

        for ($m = 1; $m <= 12; $m++) {
            $target = (int) ($results[$m]->total_target ?? 0);
            $achieved = (int) ($results[$m]->total_achieved ?? 0);

            $months[] = [
                'month' => $m,
                'target' => $target,
                'achieved' => $achieved,
                'remaining' => max($target - $achieved, 0),
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
        $limit = 5;

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
        $dateInput = $request->date
            ? Carbon::parse($request->date, 'Asia/Dhaka')
            : Carbon::now('Asia/Dhaka');

        $targetDate = $dateInput->toDateString();
        $year = $dateInput->year;
        $month = $dateInput->month;

        $query = Target::query();

        if (auth()->check() && auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id());
        }

        $todayAchieved = (clone $query)
            ->whereRaw("DATE(CONVERT_TZ(created_at, '+00:00', '+06:00')) = ?", [$targetDate])
            ->sum('achieved');

        $monthlyAchieved = (clone $query)
            ->whereRaw("YEAR(CONVERT_TZ(created_at, '+00:00', '+06:00')) = ?", [$year])
            ->whereRaw("MONTH(CONVERT_TZ(created_at, '+00:00', '+06:00')) = ?", [$month])
            ->sum('achieved');

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

        if ($user->role !== 'admin') {
            $targetQuery->where('user_id', $user->id);
        } else {
            if ($request->user_id) {
                $targetQuery->where('user_id', $request->user_id);
            }
        }

        $targets = $targetQuery->get();

        $totalTarget = $targets->sum('target');
        $totalAchieved = $targets->sum('achieved');
        $totalRemaining = $totalTarget - $totalAchieved;
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
