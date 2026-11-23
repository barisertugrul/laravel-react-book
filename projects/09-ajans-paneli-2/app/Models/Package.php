<?php

namespace App\Models;

use App\Tenancy\BelongsToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Paket kütüphanesindeki bir eklenti zip'i. Dosya herkese açık diskte DEĞİL:
 * siteler onu yalnızca süreli imzalı bir adresten indirebilir (PackageDownloadController).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $version
 * @property string $path
 * @property string $sha256
 * @property int $size
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 */
#[Fillable(['slug', 'name', 'version', 'path', 'sha256', 'size', 'uploaded_by'])]
#[Hidden(['path'])]
class Package extends Model
{
    use BelongsToAgency;

    /** Kurulduktan sonraki eklenti dosyası: klasör/klasör.php (WordPress'in çoğu eklentideki düzeni) */
    public function pluginFile(): string
    {
        return "{$this->slug}/{$this->slug}.php";
    }
}
