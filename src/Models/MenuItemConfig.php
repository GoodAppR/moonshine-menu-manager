<?php

declare(strict_types=1);

namespace MoonShine\CustomMenuManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MenuItemConfig extends Model
{
    protected $table = 'menu_item_configs';

    protected $fillable = [
        'layout_class',
        'moonshine_user_id',
        'item_key',
        'parent_key',
        'zone',
        'sort_order',
        'visible',
    ];

    protected $casts = [
        'visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function moonshineUser(): BelongsTo
    {
        return $this->belongsTo(
            \MoonShine\Laravel\Models\MoonshineUser::class,
            'moonshine_user_id'
        );
    }
}
