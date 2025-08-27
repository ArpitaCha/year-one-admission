<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Session;

class SuperUser extends Model
{
    protected $table        =   'users_master';
    protected $primaryKey   =   'u_id';
    public $timestamps      =   false;

    protected $guarded = [];

    public function role()
    {
        return $this->hasOne('App\Models\Role', "role_id", "u_role_id")->withDefault(function () {
            return new Role();
        });
    }
    public function district()
    {
        return $this->hasOne('App\Models\District', "district_id_pk", "u_inst_district")->withDefault(function () {
            return new District();
        });
    }
    public function verifierstudentassign()
    {
        return $this->hasOne('App\Models\VerifierStudentAssign', "head_verifier_id", "u_id")->withDefault(function () {
            return new VerifierStudentAssign();
        });
    }
}
