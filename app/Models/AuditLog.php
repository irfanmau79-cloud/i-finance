<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'username', 'role', 'aktivitas', 'keterangan', 'ip_address'])]
class AuditLog extends Model
{
    protected $table = 'audit_log';

    const UPDATED_AT = null;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
