<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\Models\Office\Site;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\QuoteFollowUp\QuoteFollowUpSource;
use App\Utils\Bool\BoolUtil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateFollowUpQuoteCaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'assignAndStartCase' => ['sometimes', 'boolean'],
            'siteId' => ['sometimes', 'integer'],
            'quoteFollowUpSourceId' => ['sometimes', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages([
            'site_not_allowed_given_source' => __('Site is niet toegestaan indien bron opgegeven is'),
            'source_not_allowed_given_site' => __('Bron is niet toegestaan indien site opgegeven is'),
            'site_or_source_required' => __('Site of bron is verplicht'),
        ]);

        $validator->after(function (Validator $validator) {
            if ($validator->failed()) {
                // Maybe some model was not found, let's not continue validating.
                return;
            }

            if ($this->getSite() !== null && $this->getQuoteFollowUpSource() !== null) {
                $validator->addFailure('siteId', 'site_not_allowed_given_source');
                $validator->addFailure('quoteFollowUpSourceId', 'source_not_allowed_given_site');
            }

            if ($this->getSite() === null && $this->getQuoteFollowUpSource() === null) {
                $validator->addFailure('siteId', 'site_or_source_required');
                $validator->addFailure('quoteFollowUpSourceId', 'site_or_source_required');
            }
        });
    }

    public function getSite(): ?Site
    {
        $siteId = $this->input('siteId');

        if (null !== $siteId) {
            return Site::findOrFail((int) $siteId);
        }

        return null;
    }

    public function getQuoteFollowUpSource(): ?QuoteFollowUpSource
    {
        $quoteFollowUpSourceId = $this->input('quoteFollowUpSourceId');

        if (null !== $quoteFollowUpSourceId) {
            return QuoteFollowUpSource::findOrFail((int) $quoteFollowUpSourceId);
        }

        return null;
    }

    public function getAgentSession(): AgentSession
    {
        return $this->getAgent()->getActiveAgentSession();
    }

    private function getAgent(): User
    {
        return $this->user();
    }

    public function shouldAssignAndStartCase(): bool
    {
        return BoolUtil::filterFromData($this->input('assignAndStartCase', false));
    }
}
