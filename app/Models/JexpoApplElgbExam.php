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
    public function board()
    {
        return $this->hasOne('App\Models\Board', "id", "exam_board")->withDefault(function () {
            return new Board();
        });
    }
    public function state()
    {
        return $this->hasOne('App\Models\State', "state_id_pk", "exam_state_code")->withDefault(function () {
            return new State();
        });
    }
    public function district()
    {
        return $this->hasOne('App\Models\District', "district_id_pk", "exam_district")->withDefault(function () {
            return new District();
        });
    }
}
