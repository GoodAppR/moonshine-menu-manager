<?php

declare(strict_types=1);

namespace MoonShine\CustomMenuManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MenuZoneSetting extends Model
{
    protected $table = 'menu_zone_settings';

    protected $fillable = [
        'layout_class',
        'moonshine_user_id',
        'zone',
        'key',
        'value',
    ];

    public function moonshineUser(): BelongsTo
    {
        return $this->belongsTo(
            \MoonShine\Laravel\Models\MoonshineUser::class,
            'moonshine_user_id',
        );
    }
}
