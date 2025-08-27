<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Session;

class Block extends Model
{
    protected $table        =   'jexpo_block_municipalities';
    protected $primaryKey   =   'block_id_pk';
    public $timestamps      =   false;

    protected $guarded = [];
    public function subdivision()
    {
        return $this->belongsTo(Subdivision::class, 'subdivision_id', 'id')->withDefault();
    }
}
