<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'invoice',
        'customerName',
        'customerPhone',
        'appliedCountry',
        'salesPerson',
        'usersName',
        'refundNote',
        'status',
        'manager_id',
        'admin_id',
        'finance_id',
        'rejection_reason',
    ];

    /**
     * রিলেশনশিপ: এই রিকোয়েস্টটি কোন ম্যানেজার এপ্রুভ করেছে
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * রিলেশনশিপ: এই রিকোয়েস্টটি কোন এডমিন এপ্রুভ করেছে
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * রিলেশনশিপ: এই রিকোয়েস্টটি কোন ফিন্যান্স ম্যানেজার এপ্রুভ করেছে
     */
    public function financeManager()
    {
        return $this->belongsTo(User::class, 'finance_id');
    }
}
