<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StockInventoryMovement extends Model { protected $fillable=['product_id','movement_type','quantity','reference','source','destination','notes']; public function product():BelongsTo{return $this->belongsTo(StockInventoryProduct::class,'product_id');} }
