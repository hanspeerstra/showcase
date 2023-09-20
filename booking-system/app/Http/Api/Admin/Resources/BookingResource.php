<?php

namespace App\Http\Api\Admin\Resources;

use DateTimeInterface;
use Domains\Booking\Models\Booking;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    public function __construct(Booking $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'date' => $this->getDate()->format(DateTimeInterface::ATOM),

            'arrivalDate' => $this->getArrivalDate()->format('Y-m-d'),
            'departureDate' => $this->getDepartureDate()->format('Y-m-d'),
            'nights' => $this->getNights(),

            'numberOfPersons' => $this->getNumberOfPersons(),
            'extraNumberOfPersons' => $this->getExtraNumberOfPersons(),

            'totalAmount' => $this->getTotalAmount(),
            'totalAmountPaid' => $this->isTotalAmountPaid(),
            'outstandingAmount' => $this->getOutstandingAmount(),
            'discount' => $this->getDiscount(),

            'downPayment' => $this->getDownPayment(),
            'downPaymentPaid' => $this->isDownPaymentPaid(),

            'subtotalAmount' => $this->getSubtotalAmount(),

            'couponDiscount' => $this->getCouponDiscount(),

            'deposit' => $this->getDeposit(),
            'depositPaid' => $this->isDepositPaid(),
            'depositCount' => $this->getDepositCount(),
            'depositPerPerson' => $this->getDepositPerPerson(),

            'bookingFee' => $this->getBookingFee(),

            'touristFeePerPerson' => $this->getTouristFeePerPerson(),
            'touristFeeCount' => $this->getTouristFeeCount(),
            'touristFee' => $this->getTouristFee(),

            'extraPersonSurchargePerPerson' => $this->getExtraPersonSurchargePerPerson(),
            'extraPersonSurchargeCount' => $this->getExtraPersonSurchargeCount(),
            'extraPersonSurcharge' => $this->getExtraPersonSurcharge(),

            'personalDataErased' => $this->isPersonalDataErased(),

            'notes' => $this->getNotes(),

            'trashed' => $this->isTrashed(),

            'items' => ItemResource::collection($this->getItems()),
            'fishers' => FisherResource::collection($this->getFishers()),
            'customer' => new CustomerResource($this->getCustomer()),
            'address' => $this->getAddress() === null ? null : new AddressResource($this->getAddress()),
            'coupon' => $this->getCoupon() === null ? null : new CouponResource($this->getCoupon()),

            'link' => route('admin.bookings.show', $this),
        ];
    }
}
