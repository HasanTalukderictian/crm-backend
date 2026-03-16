<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

     protected $fillable = ['name', 'department_id', 'image'];

    public function department() {
        return $this->belongsTo(Department::class);
    }

    public function visas()
    {
        return $this->hasMany(Visa::class);
    }
}
