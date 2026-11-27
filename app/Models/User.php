<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar',
        'auth_provider',
        'auth_provider_id',
        'firebase_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * The driver profile that belongs to this account.
     *
     * Registrations create the Driver row with the same phone number, so phone
     * is the join key. A driver account with no profile returns null and the
     * driver endpoints refuse it rather than guessing.
     */
    public function driver()
    {
        return $this->hasOne(Driver::class, 'phone', 'phone');
    }

    /**
     * Resolve this account's driver profile, tolerating the phone formats the
     * platform has stored over time ("+91 98220 11920", "9822011920", ...).
     */
    public function driverProfile(): ?Driver
    {
        $driver = $this->driver()->first();

        if ($driver) {
            return $driver;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $this->phone) ?? '';
        $lastTen = substr($digits, -10);

        if (strlen($lastTen) !== 10) {
            return null;
        }

        $byLike = Driver::where('phone', 'like', '%'.$lastTen.'%')->first();
        if ($byLike) {
            return $byLike;
        }

        return Driver::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?",
            ['%'.$lastTen]
        )->first();
    }

    public static function findByFirebaseId(?string $firebaseId): ?self
    {
        if (empty($firebaseId)) {
            return null;
        }

        return static::where('firebase_id', $firebaseId)
            ->orWhere(function ($query) use ($firebaseId) {
                $query->where('auth_provider', 'firebase')
                    ->where('auth_provider_id', $firebaseId);
            })->first();
    }
}
