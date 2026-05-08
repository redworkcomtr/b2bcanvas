<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'event', 'email', 'is_subscribed'])]
class NotificationSubscription extends Model
{
    protected function casts(): array
    {
        return ['is_subscribed' => 'boolean'];
    }
}
