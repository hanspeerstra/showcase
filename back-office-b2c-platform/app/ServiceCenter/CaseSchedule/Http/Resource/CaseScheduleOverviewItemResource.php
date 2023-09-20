<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseSchedule\Http\Resource;

use App\Models\Office\Profession;
use App\Models\Office\Servicetype;
use App\Models\Office\Site;
use App\ServiceCenter\CaseSchedule\CaseScheduleEntry;
use App\ServiceCenter\ServiceCenterCase\ContactRequestType;
use App\ServiceCenter\ServiceCenterCase\Http\Resource\ServiceCenterCaseResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * @mixin CaseScheduleEntry
 */
class CaseScheduleOverviewItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $professionData = null;
        $profession = $this->getProfession();
        if (null !== $profession) {
            $professionData = [
                'id' => $profession->getId(),
                'name' => $profession->getName(),
            ];
        }

        $serviceTypeData = null;
        $serviceType = $this->getServiceType();
        if (null !== $serviceType) {
            $serviceTypeData = [
                'id' => $serviceType->getId(),
                'name' => $serviceType->getName(),
            ];
        }
        $siteData = null;
        $site = $this->getSite();
        if (null !== $site) {
            $siteData = [
                'id' => $site->getId(),
                'name' => $site->getName(),
            ];
        }

        return [
            'dueAt' => $this->getDueAt()->format(\DateTimeInterface::ATOM),
            'case' => new ServiceCenterCaseResource($this->getCase()),
            'startedAt' => $this->getCase()->getStartedAt()->format(\DateTimeInterface::ATOM),
            'source' => [
                'contactRequestType' => $this->getSourceContactRequestType(),
                'profession' => $professionData,
                'professionName' => $this->getProfessionName(),
                'serviceType' => $serviceTypeData,
                'serviceTypeName' => $this->getServiceTypeName(),
                'site' => $siteData,
            ],
            'callerNumber' => null !== $this->getCallerNumber() ? $this->getCallerNumber()->formatE164() : null,
            'postalCode' => $this->getPostalCode(),
            'houseNumber' => $this->getHouseNumber(),
        ];
    }

    private function getProfession(): ?Profession
    {
        return $this->getCase()->getSourceProfession();
    }

    private function getProfessionName(): ?string
    {
        if (null !== $this->getProfession()) {
            return $this->getProfession()->getName();
        }

        if (null !== $this->getCase()->getExternalQuoteRequest()
            && null !== $this->getCase()->getExternalQuoteRequest()->getProfession()
        ) {
            return $this->getCase()->getExternalQuoteRequest()->getProfession();
        }

        return null;
    }

    public function getServiceType(): ?Servicetype
    {
        return $this->getCase()->getSourceServiceType();
    }

    private function getServiceTypeName(): ?string
    {
        if (null !== $this->getServiceType()) {
            return $this->getServiceType()->getName();
        }

        if (null !== $this->getCase()->getExternalQuoteRequest()
            && null !== $this->getCase()->getExternalQuoteRequest()->getServiceType()
        ) {
            return $this->getCase()->getExternalQuoteRequest()->getServiceType();
        }

        return null;
    }

    public function getSite(): ?Site
    {
        return $this->getCase()->getSourceSite();
    }

    public function getSourceContactRequestType(): ?string
    {
        if (null !== $this->getCase()->getSourceAppointment()) {
            return ContactRequestType::APPOINTMENT;
        }

        if (null !== $this->getCase()->getSourceTelephonySession()) {
            return ContactRequestType::CALL;
        }

        if (null !== $this->getCase()->getQuoteFollowUp()) {
            return ContactRequestType::QUOTE_FOLLOW_UP;
        }

        if (null !== $this->getCase()->getExternalQuoteRequest()) {
            return ContactRequestType::EXTERNAL_QUOTE_REQUEST;
        }

        if (null !== $this->getCase()->getSourceLead()) {
            if (null !== $this->getCase()->getSourceLead()->getCallbackRequest()) {
                return ContactRequestType::CALLBACK_REQUEST;
            }

            if (null !== $this->getCase()->getSourceLead()->getQuote()) {
                return ContactRequestType::QUOTE;
            }

            if (null !== $this->getCase()->getSourceLead()->getZoofyAppointmentRequest()) {
                return ContactRequestType::ZOOFY_APPOINTMENT;
            }
        }

        return null;
    }

    public function getPostalCode(): ?string
    {
        if (null !== $this->getCase()->getSourceCustomer()) {
            return $this->getCase()->getSourceCustomer()->getZipcode();
        }

        if (null !== $this->getCase()->getExternalQuoteRequest()) {
            return $this->getCase()->getExternalQuoteRequest()->getCustomerPostalCode();
        }

        return null;
    }

    public function getHouseNumber(): ?string
    {
        if (null !== $this->getCase()->getSourceCustomer()) {
            return $this->getCase()->getSourceCustomer()->getHouseNumberFull();
        }

        if (null !== $this->getCase()->getExternalQuoteRequest()) {
            return $this->getCase()->getExternalQuoteRequest()->getCustomerHouseNumber();
        }

        return null;
    }

    public function getCallerNumber(): ?PhoneNumber
    {
        if (null !== $this->getCase()->getSourceTelephonySession()
            && null !== $this->getCase()->getSourceTelephonySession()->getMeta()
            && null !== $this->getCase()->getSourceTelephonySession()->getMeta()->getCallerNumber()
        ) {
            return $this->getCase()->getSourceTelephonySession()->getMeta()->getCallerNumber();
        }

        if (null !== $this->getCase()->getExternalQuoteRequest()
            && null !== $this->getCase()->getExternalQuoteRequest()->getCustomerPhoneNumber()
        ) {
            return $this->getCase()->getExternalQuoteRequest()->getCustomerPhoneNumber();
        }

        return null;
    }
}
