<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\LogsModelActivity;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsModelActivity, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'user_type',
        'phone',
        'rut',
        'password',
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

    /**
     * Relación con pagos
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function broker(): HasOne
    {
        return $this->hasOne(Broker::class);
    }

    /**
     * Verificar si el usuario es administrador
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->user_type === 'admin' || $this->isSuperAdmin();
    }

    /**
     * Verificar si el usuario es super administrador
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'super admin', 'super-admin'])
            || in_array($this->user_type, ['super_admin', 'super admin', 'super-admin'], true);
    }

    /**
     * Verificar si puede gestionar valores y descuentos financieros
     */
    public function canManageFinancialValues(): bool
    {
        return $this->isAdmin() || $this->isSuperAdmin();
    }

    public const TYPE_SUPER_ADMIN = 'super_admin';

    public const TYPE_ADMIN = 'admin';

    public const TYPE_MARKETING = 'marketing';

    public const TYPE_BROKER = 'broker';

    public const TYPE_CUSTOMER = 'customer';

    public function isMarketing(): bool
    {
        return $this->hasRole('marketing') || $this->user_type === 'marketing';
    }

    /**
     * Verificar si el usuario es corredor / broker
     */
    public function isBroker(): bool
    {
        return $this->hasRole('broker') || $this->user_type === 'broker';
    }

    /**
     * Verificar si el usuario es cliente
     */
    public function isCustomer(): bool
    {
        return $this->hasRole('cliente') || in_array($this->user_type, ['customer', 'cliente'], true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() || $this->isMarketing();
    }
}
