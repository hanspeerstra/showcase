<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase;

use App\Models\Office\Lead;
use App\Models\Office\Servicetype;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ServiceCenterCase $case
 * @property string $search_method
 * @property int $result_count
 * @property ?Servicetype $servicetype
 * @property string|null $postcode
 * @property string|null $house_number
 * @property CarbonInterface $created_at
 *
 * @method static Lead|Builder|\Illuminate\Database\Query\Builder query()
 */
class ServiceCenterCaseMatchmakerSearch extends Model
{
    public const SEARCH_METHOD_MATCHMAKER = 'matchmaker';
    public const SEARCH_METHOD_MATCHMAKER_COMPANY_LEADSCREENING = 'matchmaker-company-leadscreening';
    public const SEARCH_METHOD_LEADSCREENING = 'leadscreening';

    public const SEARCH_METHODS = [
        self::SEARCH_METHOD_MATCHMAKER,
        self::SEARCH_METHOD_MATCHMAKER_COMPANY_LEADSCREENING,
        self::SEARCH_METHOD_LEADSCREENING,
    ];

    use SoftDeletes;

    protected $table = 'sc_case_matchmaker_searches';

    public $timestamps = false;

    protected $dates = ['created_at'];

    public static function makeInstance(
        ServiceCenterCase $case,
        string $searchMethod,
        int $resultCount,
        ?Servicetype $servicetype,
        ?string $postcode,
        ?string $houseNumber
    ): self {
        return (new static())
            ->setCase($case)
            ->setSearchMethod($searchMethod)
            ->setResultCount($resultCount)
            ->setServiceType($servicetype)
            ->setPostcode($postcode)
            ->setHouseNumber($houseNumber)
            ->setCreatedAt(Carbon::now());
    }

    /**
     * @internal
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ServiceCenterCase::class, 'case_id');
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    private function setCase(ServiceCenterCase $case): self
    {
        $this->case()->associate($case);

        return $this;
    }

    public function getSearchMethod(): string
    {
        return $this->search_method;
    }

    private function setSearchMethod(string $searchMethod): self
    {
        if (!in_array($searchMethod, self::SEARCH_METHODS, true)) {
            throw new UnexpectedValueException(sprintf(
                'Case (id: %s) searchMethod not found (%s)',
                $this->getCase()->getId(),
                $searchMethod
            ));
        }

        $this->search_method = $searchMethod;

        return $this;
    }

    public function servicetype(): BelongsTo
    {
        return $this->belongsTo(Servicetype::class);
    }

    private function setServiceType(?Servicetype $servicetype): self
    {
        if ($servicetype === null) {
            $this->servicetype()->dissociate();
        } else {
            $this->servicetype()->associate($servicetype);
        }

        return $this;
    }

    public function getServicetype(): ?Servicetype
    {
        return $this->servicetype;
    }

    public function getResultCount(): int
    {
        return $this->result_count;
    }

    private function setResultCount(int $resultCount): self
    {
        $this->result_count = $resultCount;

        return $this;
    }

    public function getPostcode(): ?string
    {
        return $this->postcode;
    }

    private function setPostcode(?string $postcode): self
    {
        $this->postcode = $postcode;

        return $this;
    }

    public function getHouseNumber(): ?string
    {
        return $this->house_number;
    }

    private function setHouseNumber(?string $houseNumber): self
    {
        $this->house_number = $houseNumber;

        return $this;
    }

    public function setCreatedAt($value): self
    {
        $this->created_at = $value;

        return $this;
    }

    public function getCreatedAt(): CarbonInterface
    {
        return $this->created_at;
    }

    public function isSearchEqual(ServiceCenterCaseMatchmakerSearch $other): bool
    {
        return $this->getSearchMethod() === $other->getSearchMethod()
            && (
                ($this->getServicetype()  === null && $other->getServicetype() === null)
                || ($this->getServicetype() !== null && $this->getServicetype()->is($other->getServicetype()))
            )
            && $this->getPostcode() === $other->getPostcode()
            && $this->getHouseNumber() === $other->getHouseNumber();
    }
}
