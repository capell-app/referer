<?php

declare(strict_types=1);

namespace Capell\Referer\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

final class RefererSourceTotal extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $table = 'referer_source_totals';

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['count' => 'integer', 'collection_started_on' => 'date:Y-m-d'];
    }
}
