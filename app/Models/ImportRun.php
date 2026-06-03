<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRun extends Model
{
    protected $fillable = [
        'account_id',
        'user_id',
        'status',
        'original_filename',
        'stored_path',
        'name_column',
        'first_name_column',
        'last_name_column',
        'email_column',
        'business_name_column',
        'website_column',
        'group_ids',
        'total_rows',
        'processed_rows',
        'imported_rows',
        'skipped_rows',
        'failed_rows',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'group_ids' => 'array',
        'failed_rows' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }

    public function progressPercent(): int
    {
        if ($this->total_rows <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->processed_rows / $this->total_rows) * 100));
    }
}
