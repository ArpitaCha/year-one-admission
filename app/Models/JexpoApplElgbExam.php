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
    public function stateboard()
    {
        return $this->hasOne('App\Models\Board', "state_code", "exam_state_code")->withDefault(function () {
            return new Board();
        });
    }
}
