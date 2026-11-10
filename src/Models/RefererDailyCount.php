<?php

declare(strict_types=1);

namespace Capell\Referer\Models;

use Capell\Core\Concerns\HasCapellTable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

final class RefererDailyCount extends Model
{
    use HasCapellTable;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $table = 'referer_daily_counts';

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'count' => 'integer'];
    }
}
