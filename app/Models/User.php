<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $created_by_user
 * @property string $name
 * @property string $email
 * @property string|null $extension
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'extension', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Customers this user owns (assigned_to).
     *
     * @return HasMany<Customer, $this>
     */
    public function assignedCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'assigned_to');
    }

    /**
     * Interactions this user handled.
     *
     * @return HasMany<Interaction, $this>
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    // --- Hierarchy (DESIGN_HIERARCHY.md batch H1) ---------------------------
    // Dormant: defined so later batches (scoping, dashboard, delegated creation)
    // have the relations to build on. NOTHING here is read by a scope/gate yet.

    /**
     * The user who provisioned this account (delegated-creation trail, DH1/DH4).
     *
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }

    /**
     * Accounts this user provisioned.
     *
     * @return HasMany<User, $this>
     */
    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by_user');
    }

    /**
     * Teams this user belongs to (DH1). A pivot for DH6 multi-team; today DH2
     * keeps it to one — use team() for that single team.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot('role_in_team')
            ->withTimestamps();
    }

    /**
     * The user's single team (DH2 convenience — one team per user for now).
     * Not an Eloquent relation: call as a method, e.g. $user->team().
     */
    public function team(): ?Team
    {
        return $this->teams()->first();
    }

    /**
     * CS/Maintenance users assigned to help this Sales user (DH5).
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sales_assignee', 'sales_user_id', 'assignee_user_id')
            ->withTimestamps();
    }

    /**
     * The Sales users this CS/Maintenance account is assigned to (inverse of
     * assignees(), DH5).
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignedSalesFor(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sales_assignee', 'assignee_user_id', 'sales_user_id')
            ->withTimestamps();
    }
}
