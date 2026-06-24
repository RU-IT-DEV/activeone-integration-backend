<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\SoftDeletes;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status'
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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected static function booted() {
        static::creating(function ($user) {
            // Set a default password if none is provided
            if (empty($user->password)) {
                $user->password = Hash::make('HAVrV9tTlMdHazsuEQXtBE8WOuac68SIOBdH6WU4'); #For laravel passport purpose
            }
        });
    }

    public function role() {
        return $this->hasOne(Roles::class, 'id', 'role_id');
    }

    public function companyAccesses()
    {
        return $this->hasMany(UserCompanyAccess::class);
    }

}
