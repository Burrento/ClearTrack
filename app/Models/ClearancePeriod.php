<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['department_id', 'status', 'fine_tiers', 'activated_at'])]
class ClearancePeriod extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fine_tiers' => 'array',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * Get the department that owns this clearance period.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Match a student's total penalty points to the appropriate fine tier.
     * Returns the matching tier or null if no tier matches.
     *
     * @param  int  $points
     * @return array|null  {min_points, max_points, fine_amount, requirements}
     */
    public function matchTier(int $points): ?array
    {
        if (!$this->fine_tiers || $points <= 0) {
            return null;
        }

        // Sort tiers by min_points descending so we match the highest tier first
        $tiers = collect($this->fine_tiers)->sortByDesc('min_points');

        foreach ($tiers as $tier) {
            $min = (int) $tier['min_points'];
            $max = isset($tier['max_points']) && $tier['max_points'] !== null && $tier['max_points'] !== ''
                ? (int) $tier['max_points']
                : PHP_INT_MAX;

            if ($points >= $min && $points <= $max) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Check if this clearance period is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
