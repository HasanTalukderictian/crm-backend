<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    use HasFactory;

     protected $fillable = [
        'visa_id',
        'phone',
        'message',
        'type'
    ];

    public function visa()
    {
        return $this->belongsTo(Visa::class);
    }
}
