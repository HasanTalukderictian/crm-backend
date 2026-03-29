<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visa extends Model
{
    use HasFactory;

     protected $fillable = [
        'name',
        'phone',
        'passport',
        'country',
        'invoice',
          'user_id',
        'sales_person',
        'date',
        'asset_valuation',
        'salary_amount',
        'member',
        'image',
        'bank_certificate',
        'nid_file',
        'birth_certificate',
        'marriage_certificate',
        'fixed_deposit_certificate',
        'tax_certificate',
        'tin_certificate',
        'credit_card_copy',
        'covid_certificate',
        'noc_letter',
        'office_id',
        'salary_slips',
        'government_order',
        'visiting_card',
        'company_bank_statement',
        'blank_office_pad',
        'renewal_trade_license',
        'memorandum_limited',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
{
    return $this->belongsTo(User::class);
}
}
