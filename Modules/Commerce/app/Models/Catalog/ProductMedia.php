<?php

namespace Modules\Commerce\Models\Catalog;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'media_type', 'storage_disk', 'storage_path', 'original_filename', 'mime_type',
    'size_bytes', 'width', 'height', 'alt_text', 'position', 'is_featured',
])]
class ProductMedia extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'media_type' => 'image',
        'position' => 0,
        'is_featured' => false,
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}