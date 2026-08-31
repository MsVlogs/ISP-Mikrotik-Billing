<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetworkInventoryDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'type','name','ip_address','vendor','model','status','location','notes',
    ];

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
