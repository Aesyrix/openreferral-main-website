<?php

namespace App\Models;

use App\Enums\LeadIntent;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_id',
        'name',
        'avatar_url',
        'phone',
        'email',
        'intent',
        'property_type',
        'price_min',
        'price_max',
        'location',
        'timeline',
        'bedrooms',
        'bathrooms',
        'source',
        'notes',
        'lead_fee_cents',
        'status',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'responded_at',
        'converted_at',
        'created_by',
    ];

    protected $casts = [
        'intent' => LeadIntent::class,
        'status' => LeadStatus::class,
        'price_min' => 'integer',
        'price_max' => 'integer',
        'lead_fee_cents' => 'integer',
        'assigned_at' => 'datetime',
        'responded_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $lead) {
            if (empty($lead->reference_id)) {
                $lead->reference_id = static::nextReference();
            }
        });
    }

    /**
     * Sequential human reference (LEAD-10001, LEAD-10002, ...). Derived from the
     * highest existing reference rather than the id so gaps never repeat a code.
     */
    public static function nextReference(): string
    {
        $highest = (int) static::withTrashed()
            ->where('reference_id', 'like', 'LEAD-%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference_id, 6) AS UNSIGNED)) AS n')
            ->value('n');

        return 'LEAD-'.max($highest + 1, 10001);
    }

    // ---------------------------------------------------------------- Scopes

    public function scopeAssignedTo(Builder $query, int $agentId): Builder
    {
        return $query->where('assigned_to', $agentId);
    }

    public function scopeStatus(Builder $query, LeadStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof LeadStatus ? $status->value : $status);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term) {
            $like = '%'.$term.'%';
            $inner->where('reference_id', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('location', 'like', $like)
                ->orWhere('property_type', 'like', $like);
        });
    }

    /** Sort keys the mobile app sends: newest|oldest|price_high|price_low|name_az */
    public function scopeSorted(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'price_high' => $query->orderByRaw('COALESCE(price_max, price_min, 0) DESC'),
            'price_low' => $query->orderByRaw('COALESCE(price_min, price_max, 0) ASC'),
            'name_az' => $query->orderBy('name'),
            default => $query->orderByDesc('created_at'),
        };
    }

    // ---------------------------------------------------------- Relationships

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments()
    {
        return $this->hasMany(LeadAssignment::class, 'lead_id')->orderByDesc('assigned_at');
    }

    public function feedback()
    {
        return $this->hasMany(LeadFeedback::class, 'lead_id')->orderByDesc('created_at');
    }

    public function latestFeedback()
    {
        return $this->hasOne(LeadFeedback::class, 'lead_id')->latestOfMany('id');
    }

    public function statusHistory()
    {
        return $this->hasMany(LeadStatusHistory::class, 'lead_id')->orderByDesc('id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'lead_id');
    }

    // -------------------------------------------------------------- Accessors

    public function getPriceRangeAttribute(): ?string
    {
        if (! $this->price_min && ! $this->price_max) {
            return null;
        }
        if ($this->price_min && $this->price_max) {
            return '$'.number_format($this->price_min).' – $'.number_format($this->price_max);
        }

        return '$'.number_format($this->price_max ?: $this->price_min);
    }

    public function getLeadFeeDollarsAttribute(): float
    {
        return round($this->lead_fee_cents / 100, 2);
    }

    /** Hours between assignment and the agent's decision, if both happened. */
    public function responseHours(): ?float
    {
        if (! $this->assigned_at || ! $this->responded_at) {
            return null;
        }

        return round($this->assigned_at->diffInMinutes($this->responded_at) / 60, 2);
    }

    public function isRespondable(): bool
    {
        return $this->status === LeadStatus::NEW && $this->assigned_to !== null;
    }
}
