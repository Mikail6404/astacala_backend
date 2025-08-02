<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'profile_picture_url',
        'role',
        'organization',
        'birth_date',
        'emergency_contacts',
        'is_active',
        'email_verified',
        'fcm_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'password' => 'hashed',
            'emergency_contacts' => 'array',
            'is_active' => 'boolean',
            'email_verified' => 'boolean',
        ];
    }

    /**
     * Get disaster reports created by this user.
     */
    public function disasterReports()
    {
        return $this->hasMany(DisasterReport::class, 'reported_by');
    }

    /**
     * Get disaster reports assigned to this user.
     */
    public function assignedReports()
    {
        return $this->hasMany(DisasterReport::class, 'assigned_to');
    }

    /**
     * Get notifications for this user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id')
            ->orWhere('recipient_id', $this->id);
    }
}
