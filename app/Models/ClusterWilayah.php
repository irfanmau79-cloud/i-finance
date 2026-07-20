<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cluster_id', 'nama_wilayah'])]
class ClusterWilayah extends Model
{
    protected $table = 'cluster_wilayah';

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(ClusterUh::class, 'cluster_id');
    }
}
