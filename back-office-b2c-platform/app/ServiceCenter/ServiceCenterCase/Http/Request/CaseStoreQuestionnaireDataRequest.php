<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Questionnaire\AnswerBag\Answer;
use App\Questionnaire\AnswerBag\AnswerBag;
use App\Questionnaire\AnswerBag\Factory\AnswerFactory;
use App\Questionnaire\Question\Question;
use App\Questionnaire\Question\Repository\PredefinedAnswerRepository;
use App\Questionnaire\Question\Repository\QuestionRepository;
use App\Questionnaire\QuestionList\QuestionList;
use App\Questionnaire\QuestionList\Repository\QuestionListRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property ServiceCenterCase $case
 */
class CaseStoreQuestionnaireDataRequest extends FormRequest
{
    /** @var AnswerBag|null */
    private $answerBag;

    /** @var AnswerFactory|null */
    private $answerFactory;

    /** @var Answer[]|null */
    private $answers;

    /** @var QuestionRepository|null */
    private $questionRepository;

    /** @var PredefinedAnswerRepository|null */
    private $predefinedAnswerRepository;

    /** @var Question[]|null */
    private $questions;

    public function rules(): array
    {
        return [
            'questionListId' => ['required', 'integer'],
            'questions' => ['required', 'array'],
            'questions.*.questionId' => ['required', 'integer'],
            'questions.*.answers' => ['required', 'array'],
            'questions.*.answers.*.type' => [
                'required',
                Rule::in([
                    Question::QUESTION_TYPE_PREDEFINED,
                    Question::QUESTION_TYPE_OPEN,
                ]),
            ],
            'questions.*.answers.*.predefinedAnswerId' => ['sometimes', 'integer'],
            'questions.*.answers.*.value' => ['sometimes', 'string'],
        ];
    }

    public function getQuestionList(): QuestionList
    {
        $questionListRepository = $this->container->make(QuestionListRepository::class);

        return $questionListRepository->getById(
            (int) $this->input('questionListId')
        );
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getAnswerBag(): AnswerBag
    {
        if ($this->answerBag === null) {
            $this->answerBag = $this->getCase()->getAnswerBag();
        }

        return $this->answerBag;
    }

    /**
     * @return Answer[]
     */
    public function getAnswers(): iterable
    {
        if ($this->answers === null) {
            $questions = $this->input('questions');

            foreach ($questions as $questionInput) {
                $questionId = (int) $questionInput['questionId'];
                $question = $this->getQuestion($questionId);

                foreach ($questionInput['answers'] as $answerInput) {
                    $this->answers[] = $this->getAnswer($question, $answerInput);
                }
            }
        }

        return $this->answers;
    }

    private function getAnswer(Question $question, array $answerInput): Answer
    {
        if ($answerInput['type'] === Question::QUESTION_TYPE_PREDEFINED) {
            $predefinedAnswer = $this->getPredefinedAnswerRepository()->getById(
                (int) $answerInput['predefinedAnswerId']
            );

            return $this->getAnswerFactory()->createPredefined(
                $this->getAnswerBag(),
                $question,
                $predefinedAnswer
            );
        }

        $value = $answerInput['value'];

        return $this->getAnswerFactory()->createOpen(
            $this->getAnswerBag(),
            $question,
            $value
        );
    }

    private function getAnswerFactory(): AnswerFactory
    {
        if ($this->answerFactory === null) {
            $this->answerFactory = $this->container->make(AnswerFactory::class);
        }

        return $this->answerFactory;
    }

    private function getQuestionRepository(): QuestionRepository
    {
        if ($this->questionRepository === null) {
            $this->questionRepository = $this->container->make(QuestionRepository::class);
        }

        return $this->questionRepository;
    }

    private function getPredefinedAnswerRepository(): PredefinedAnswerRepository
    {
        if ($this->predefinedAnswerRepository === null) {
            $this->predefinedAnswerRepository = $this->container->make(PredefinedAnswerRepository::class);
        }

        return $this->predefinedAnswerRepository;
    }

    private function getQuestion(int $questionId): Question
    {
        if (!isset($this->questions[$questionId])) {
            $this->questions[$questionId] = $this->getQuestionRepository()->getById($questionId);
        }

        return $this->questions[$questionId];
    }
}
