<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JexpoApplElgbExam extends Model
{
    // use HasFactory;
    protected $table        =   'appl_elgb_exam';
    protected $primaryKey   =   'id';
    public $timestamps      =   false;

    protected $guarded = [];
}
