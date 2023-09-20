<?php

namespace Domains\Booking\Models;

use DateTime;
use DateTimeInterface;
use Domains\Booking\BookingQueryBuilder;
use Domains\Booking\Payment;
use Domains\Coupon\Models\Coupon;
use Domains\Coupon\DiscountType;
use Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property boolean $personal_data_erased
 * 
 * @method static static|BookingQueryBuilder|Builder|\Illuminate\Database\Query\Builder query()
 */
class Booking extends Model
{
    public function isPersonalDataErased(): bool
    {
        return $this->personal_data_erased;
    }

    public function markPersonalDataAsErased(): self
    {
        $this->personal_data_erased = true;

        return $this;
    }

    public function newEloquentBuilder($query): BookingQueryBuilder
    {
        return new BookingQueryBuilder($query);
    }
}
