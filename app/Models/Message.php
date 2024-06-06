<?php

namespace App\Models;

use App\Http\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, CreatedUpdatedBy, SoftDeletes;

    protected $table = 'message';

    protected $fillable = [
        'message_type',
        'attachment_type',
        'message',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $auditEvents = [
        'created',
        'updated',
        'deleted'
    ];

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function tasks()
    {
        return $this->hasMany(MessageTask::class);
    }

    public function locations()
    {
        return $this->hasMany(MessageLocation::class);
    }

    public function meetings()
    {
        return $this->hasMany(MessageMeeting::class);
    }

    public function senderReceiver()
    {
        return $this->hasMany(MessageSenderReceiver::class);
    }

    public function attachment()
    {
        return $this->hasOne(MessageAttachment::class);
    }

    public function task()
    {
        return $this->hasOne(MessageTask::class);
    }

    public function location()
    {
        return $this->hasOne(MessageLocation::class);
    }

    public function meeting()
    {
        return $this->hasOne(MessageMeeting::class);
    }

    public function taskChats()
    {
        return $this->hasMany(MessageTaskChat::class);
    }

    public function senderReceiverOne()
    {
        return $this->hasOne(MessageSenderReceiver::class);
    }
}
