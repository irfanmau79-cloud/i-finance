<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Pesan terakhir yang sudah dibaca satu akun di satu ruang. */
#[Fillable(['chat_ruang_id', 'user_id', 'dibaca_sampai_id'])]
class ChatBaca extends Model
{
    protected $table = 'chat_baca';
}
