<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Questionnaire\QuestionList\QuestionList;
use App\Questionnaire\QuestionList\Repository\QuestionListRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property ServiceCenterCase $case
 */
class CaseShowQuestionnaireDataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'questionListLabel' => ['required', 'string'],
        ];
    }

    public function getQuestionList(): QuestionList
    {
        $questionListRepository = $this->container->make(QuestionListRepository::class);

        return $questionListRepository->getByLabel(
            $this->input('questionListLabel')
        );
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }
}
