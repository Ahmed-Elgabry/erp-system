<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsSettingNotification extends Model
{
    use HasFactory;

    protected $fillable = ['name' , 'value' , 'Created_by'];
}
