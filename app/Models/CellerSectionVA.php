<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CellerSectionVA extends Model
{
    // protected $connection = 'vizapi';
    protected $table = 'warehouse_sections';
    public $timestamps = false;

    private $rootCache = null;
    protected $fillable = ['name',
                    'alias',
                    'path' ,
                    '_root',
                    'deep',
                    'details',
                    '_warehouse'];

    public function warehouse(){
        return $this->belongsTo('App\Models\Warehouses', '_warehouse');
    }

    public function parent(){
        return $this->belongsTo(CellerSectionVA::class, '_root');
    }

    public function getRootNode(){
        if ($this->rootCache) {
            return $this->rootCache;
        }
        $parent = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();
        if (!$parent) {
            $this->rootCache = $this;
        } else {
            $this->rootCache = $parent->getRootNode();
        }
        return $this->rootCache;
    }

    public function children(){
        return $this->hasMany(CellerSectionVA::class, '_root', 'id');
    }

    public function getAllDescendantIds(){
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }
}
