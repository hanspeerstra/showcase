<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Questionnaire\QuestionList\QuestionList;
use App\Repositories\Office\ServiceTypeRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @property ServiceCenterCase $case
 */
class CaseShowQuestionnaireDataByServiceTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'serviceTypeId' => ['required', 'integer'],
        ];
    }

    public function getQuestionList(): QuestionList
    {
        /** @var ServiceTypeRepository $serviceTypeRepository */
        $serviceTypeRepository = $this->container->make(ServiceTypeRepository::class);

        $serviceTypeId = (int) $this->input('serviceTypeId');

        $questionList = $serviceTypeRepository
            ->getById($serviceTypeId)
            ->getServiceCenterQuestionList();

        if (null === $questionList) {
            throw new NotFoundHttpException();
        }

        return $questionList;
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }
}
