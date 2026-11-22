<?php

namespace App\Models;

use App\Enums\ContactType;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property ContactType $type
 * @property string $name
 * @property string|null $tax_number
 * @property string|null $tax_office
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 */
#[Fillable(['type', 'name', 'tax_number', 'tax_office', 'email', 'phone', 'address'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['type' => ContactType::class];
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Carinin bütün ödemeleri, faturaları üzerinden: contacts → invoices → payments */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Invoice::class);
    }

    /**
     * Cari bakiye (kuruş): iptal edilmemiş faturaların toplamı − ödenenler.
     * Müşteride pozitif bakiye = bize borçlu; tedarikçide = bizim ona borcumuz.
     */
    public function balance(): int
    {
        $invoices = $this->invoices()->whereNull('cancelled_at');

        return (int) $invoices->sum('total') - (int) $invoices->sum('paid_total');
    }
}
