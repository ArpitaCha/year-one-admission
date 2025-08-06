<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Institute extends Model
{
    protected $table        =   'institute_master';
    protected $primaryKey   =   'i_id';
    public $timestamps      =   false;

    protected $guarded = [];
}
