<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RefundRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RefundController extends Controller
{
    /**
     * ১. রিফান্ড রিকোয়েস্ট স্টোর করা (Frontend Form থেকে)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice' => 'required|unique:refund_requests,invoice',
            'customerName' => 'required',
            'customerPhone' => 'required',
            'refundNote' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            RefundRequest::create([
                'invoice' => $request->invoice,
                'customerName' => $request->customerName,
                'customerPhone' => $request->customerPhone,
                'appliedCountry' => $request->appliedCountry,
                'salesPerson' => $request->salesPerson,
                'usersName' => Auth::user()->name, // বর্তমানে লগইন থাকা ইউজারের নাম
                'refundNote' => $request->refundNote,
                'status' => 'pending', // প্রাথমিক স্ট্যাটাস
            ]);

            return response()->json(['status' => true, 'message' => 'Refund request submitted to Manager.']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Something went wrong!'], 500);
        }
    }

    /**
     * ২. রিফান্ড লিস্ট দেখা (রোল অনুযায়ী ফিল্টার হবে)
     */
    public function index()
    {
        $user = Auth::user();
        $query = RefundRequest::query();

        // রোল অনুযায়ী ডাটা ফিল্টার
        if ($user->role === 'manager') {
            $query->where('status', 'pending');
        } elseif ($user->role === 'admin') {
            $query->where('status', 'manager_approved');
        } elseif ($user->role === 'finance_manager') {
            $query->where('status', 'admin_approved');
        }

        $data = $query->latest()->get();
        return response()->json(['status' => true, 'data' => $data]);
    }

    /**
     * ৩. স্ট্যাটাস আপডেট (Approve/Reject) লজিক
     */
    public function updateStatus(Request $request, $id)
    {
        $refund = RefundRequest::findOrFail($id);
        $user = Auth::user();
        $action = $request->action; // 'approve' অথবা 'reject'

        if ($action === 'reject') {
            $refund->update([
                'status' => 'rejected',
                'rejection_reason' => $request->reason
            ]);
            return response()->json(['status' => true, 'message' => 'Refund request rejected.']);
        }

        // Approval Flow Logic
        if ($user->role === 'manager' && $refund->status === 'pending') {
            $refund->update(['status' => 'manager_approved', 'manager_id' => $user->id]);
            return response()->json(['status' => true, 'message' => 'Manager approved. Sent to Admin.']);
        }

        elseif ($user->role === 'admin' && $refund->status === 'manager_approved') {
            $refund->update(['status' => 'admin_approved', 'admin_id' => $user->id]);
            return response()->json(['status' => true, 'message' => 'Admin approved. Sent to Finance Manager.']);
        }

        elseif ($user->role === 'finance_manager' && $refund->status === 'admin_approved') {
            $refund->update(['status' => 'completed', 'finance_id' => $user->id]);

            // SMS পাঠানোর মেথড কল
            $this->sendSMS($refund->customerPhone, $refund->invoice);

            return response()->json(['status' => true, 'message' => 'Refund completed and SMS sent.']);
        }

        return response()->json(['status' => false, 'message' => 'Unauthorized action for your role.'], 403);
    }

    /**
     * ৪. এসএমএস পাঠানোর হেল্পার
     */
    private function sendSMS($phone, $invoice)
    {
        $message = "Your refund request for Invoice: {$invoice} has been released. Thank you!";
        // আপনার প্রোজেক্টের বিদ্যমান SMS Function এখানে কল করুন
        // Example: send_sms_helper($phone, $message);
    }
}
