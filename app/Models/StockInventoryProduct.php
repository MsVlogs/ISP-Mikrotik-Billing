<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\HasMany;
class StockInventoryProduct extends Model { protected $fillable=['sku','name','category','unit','quantity','reorder_level','unit_cost','status','notes']; protected $casts=['unit_cost'=>'decimal:2']; public function movements():HasMany{return $this->hasMany(StockInventoryMovement::class,'product_id');} }
