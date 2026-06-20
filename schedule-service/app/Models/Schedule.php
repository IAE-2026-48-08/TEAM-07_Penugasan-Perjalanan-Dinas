<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'destination',
        'departure_date',
        'return_date',
        'purpose',
        'status'
    ];
}