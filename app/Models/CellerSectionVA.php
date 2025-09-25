<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CellerSectionVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'celler_section';
    public $timestamps = false;

    private $rootCache = null;
    protected $fillable = ['name',
                    'alias',
                    'path' ,
                    'root',
                    'deep',
                    '_celler'];

    public function celler(){
        return $this->belongsTo('App\Models\CellerVA', '_celler');
    }

    public function parent(){
        return $this->belongsTo(CellerSectionVA::class, 'root');
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
        return $this->hasMany(CellerSectionVA::class, 'root', 'id');
    }

    public function getAllDescendantIds(){
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }
}
