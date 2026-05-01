<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'subject', 'message',
        'ip_address', 'user_agent', 'is_read', 'is_archived', 'replied_at',
    ];

    protected $casts = [
        'is_read'     => 'boolean',
        'is_archived' => 'boolean',
        'replied_at'  => 'datetime',
    ];

    public function scopeUnread($q)
    {
        return $q->where('is_read', false)->where('is_archived', false);
    }

    public function scopeInbox($q)
    {
        return $q->where('is_archived', false);
    }
}
