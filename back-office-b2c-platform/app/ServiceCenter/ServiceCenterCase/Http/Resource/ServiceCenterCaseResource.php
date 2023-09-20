<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Resource;

use App\ContactRequests\Http\Resource\AppointmentResource;
use App\ContactRequests\Http\Resource\QuoteResource;
use App\ExternalQuoteRequest\Http\Resource\ExternalQuoteRequestResource;
use App\Helpers\View\Servicenumbers;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\ContactmethodResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\ProfessionResource;
use App\Http\Resources\RegionResource;
use App\Http\Resources\ServicetypeResource;
use App\Http\Resources\SiteResource;
use App\Http\Resources\SubscriptionResource;
use App\ServiceCenter\QuoteFollowUp\Http\Resource\QuoteFollowUpResource;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Telephony\Session\Http\Resource\TelephonySessionResource;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceCenterCase
 */
class ServiceCenterCaseResource extends JsonResource
{
    /**
     * @inheritdoc
     */
    public function toArray($request): array
    {
        $caseType = $this->getCaseEntry()->getCaseType();
        $workGroup = $this->getCaseEntry()->getWorkGroup();
        $assignedAgent = $this->getCaseEntry()->getAssignedAgent();

        $latestAssignedAgent = null;
        if ($this->getLatestAgentSessionLogEntry() !== null) {
            $latestAssignedAgent = $this->getLatestAgentSessionLogEntry()->getAgentSessionWithTrashed()->getUser();
        }

        $telephonySessionResource = null;
        $sourceTelephonySessionResource = null;
        $sourceAppointmentResource = null;
        $matchmakerSearchResource = null;

        if ($this->getActiveTelephonySession() !== null) {
            $telephonySessionResource = new TelephonySessionResource($this->getActiveTelephonySession());
        }

        if ($this->getSourceTelephonySession() !== null) {
            $sourceTelephonySessionResource = new TelephonySessionResource($this->getSourceTelephonySession());
        }

        if ($this->getCaseMatchmakerSearch() !== null) {
            $matchmakerSearchResource = new ServiceCenterCaseMatchmakerSearchResource($this->getCaseMatchmakerSearch());
        }

        $telephonyTrackingSegment = $this->getSourceServicenumberLinkTrackingSegment();

        return [
            'id' => $this->getId(),
            'createdAt' => self::formatDateTime($this->getCreatedAt()),
            'startedAt' => self::formatDateTime($this->getStartedAt()),
            'closedAt' => self::formatDateTime($this->getClosedAt()),
            'isClosed' => $this->isClosed(),
            'isPaused' => $this->isPaused(),
            'isQueued' => $this->isQueued(),

            // CaseEntry
            'caseType' => [
                'id' => $caseType->getId(),
                'name' => $caseType->getName(),
            ],
            'workGroup' => [
                'id' => $workGroup->getId(),
                'name' => $workGroup->getName(),
            ],
            'assignedUser' => [
                'id' => null !== $assignedAgent ? $assignedAgent->getId() : null,
                'firstName' => null !== $assignedAgent ? $assignedAgent->getFirstName() : null,
                'lastName' => null !== $assignedAgent ? $assignedAgent->getLastName() : null,
            ],
            'latestAssignedUser' => [
                'id' => null !== $latestAssignedAgent ? $latestAssignedAgent->getId() : null,
                'firstName' => null !== $latestAssignedAgent ? $latestAssignedAgent->getFirstName() : null,
                'lastName' => null !== $latestAssignedAgent ? $latestAssignedAgent->getLastName() : null,
            ],

            'source' => [
                'contactMethod' => $this->getSourceContactMethod(),
                'leadContactMethod' => $this->getSourceLeadContactMethod() !== null
                    ? new ContactmethodResource($this->getSourceLeadContactMethod())
                    : null,
                'region' => $this->getSourceRegion() !== null ? new RegionResource($this->getSourceRegion()) : null,
                'profession' => $this->getSourceProfession() !== null
                    ? new ProfessionResource($this->getSourceProfession())
                    : null,
                'serviceType' => $this->getSourceServiceType() !== null
                    ? new ServicetypeResource($this->getSourceServiceType())
                    : null,
                'site' => $this->getSourceSite() !== null ? new SiteResource($this->getSourceSite()) : null,
                'serviceNumberType' => $this->getSourceServicenumberLinkTypeLabel() !== null ? [
                    'label' => $this->getSourceServicenumberLinkTypeLabel(),
                    'name' => Servicenumbers::LINK_TYPE_NAMES[$this->getSourceServicenumberLinkTypeLabel()],
                ] : null,
                'serviceNumberTrackingSegment' => $telephonyTrackingSegment !== null ? [
                    'name' => $telephonyTrackingSegment->getDisplayName(),
                    'isSea' => $telephonyTrackingSegment->isSea(),
                ] : null,
                'quoteFollowUp' => null !== $this->getQuoteFollowUp()
                    ? new QuoteFollowUpResource($this->getQuoteFollowUp())
                    : null,
                'externalRequest' => null !== $this->getExternalQuoteRequest()
                    ? new ExternalQuoteRequestResource($this->getExternalQuoteRequest())
                    : null,
                'customer' => $this->getSourceCustomer() !== null ? new CustomerResource(
                    $this->getSourceCustomer()
                ) : null,
                'company' => $this->getSourceCompany() !== null ? new CompanyResource($this->getSourceCompany()) : null,
                'subscription' => $this->getSourceSubscription() !== null ?
                    new SubscriptionResource($this->getSourceSubscription()) :
                    null,
                'serviceNumberLabel' => $this->getSourceServicenumberLinkSystemLabel() !== null ? [
                    'label' => $this->getSourceServicenumberLinkSystemLabel(),
                    'name' => Servicenumbers::SYSTEM_LABEL_NAMES[$this->getSourceServicenumberLinkSystemLabel()],
                ] : null,
            ],

            'sourceTelephonySession' => $sourceTelephonySessionResource,

            'sourceAppointment' => $this->getSourceAppointment() !== null
                ? new AppointmentResource($this->getSourceAppointment())
                : null,

            'sourceQuote' => $this->getSourceLeadQuote() !== null
                ? new QuoteResource($this->getSourceLeadQuote())
                : null,

            'notes' => ServiceCenterCaseNoteResource::collection($this->getCaseNotes()),

            'telephonySession' => $telephonySessionResource,

            'matchmakerSearch' => $matchmakerSearchResource,

            'hasResult' => $this->hasResult(),
            'lead' => null,
            'garbageReason' => $this->getGarbageReason() !== null
                ? new ServiceCenterCaseGarbageReasonResource($this->getGarbageReason())
                : null,
            'isMatchmaker' => $caseType->isMatchmaker(),
            'isSalesOpportunity' => $caseType->isSalesOpportunity(),
            'isQuoteFollowUpCall' => $caseType->isQuoteFollowUpCall(),
            'isLeadScreening' => $caseType->isLeadScreening(),
            'isCompanyMatchmakerLeadScreening' => $caseType->isCompanyMatchmakerLeadScreening(),
            'isAppointmentCase' => $caseType->isAppointmentCase(),
            'isExternalQuoteRequest' => $caseType->isExternalQuoteRequest(),
            'hasLeadCall' => $this->hasLeadCall(),
        ];
    }

    private static function formatDateTime(?DateTimeInterface $dateTime): ?string
    {
        if (null === $dateTime) {
            return null;
        }

        return $dateTime->format(DateTimeInterface::ATOM);
    }
}
