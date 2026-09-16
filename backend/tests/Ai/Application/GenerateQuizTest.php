<?php

namespace App\Tests\Ai\Application;

use App\Ai\Application\AiQuizGenerator;
use App\Ai\Application\Dto\GenerateQuizInput;
use App\Ai\Application\GenerateQuiz;
use App\Ai\Domain\Exception\AiGenerationException;
use App\Ai\Domain\Model\AiKey;
use App\Identity\Domain\Model\User;
use App\Quiz\Application\QuizWriter;
use App\Quiz\Domain\Model\Quiz;
use App\Shared\Application\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class GenerateQuizTest extends TestCase
{
    private const DRAFT = [
        'title' => 'Les volcans',
        'description' => null,
        'category' => 'Sciences',
        'difficulty' => 'moyen',
        'questions' => [['text' => 'Quel volcan domine Naples ?', 'choices' => ['Etna', 'Vésuve'], 'correctIndex' => 1, 'explanation' => null]],
    ];

    /** Publier le quiz et décompter la génération se font dans la même transaction. */
    public function testThePublishedQuizAndTheConsumedGenerationShareOneTransaction(): void
    {
        $key = new AiKey('CODE', 5);
        $inTransaction = false;

        $writer = $this->createMock(QuizWriter::class);
        $writer->expects($this->once())->method('create')->willReturnCallback(function () use (&$inTransaction): Quiz {
            $this->assertTrue($inTransaction, 'Le quiz doit être écrit dans la transaction.');

            return new Quiz();
        });

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->once())->method('transactional')->willReturnCallback(function (callable $operation) use (&$inTransaction, $key) {
            $inTransaction = true;
            $result = $operation();
            $this->assertSame(4, $key->getRemainingGenerations(), 'La génération doit être décomptée dans la transaction.');
            $inTransaction = false;

            return $result;
        });
        $unitOfWork->expects($this->once())->method('flush');

        $this->generateQuiz(self::DRAFT, $writer, $unitOfWork)->generate(new GenerateQuizInput(topic: 'volcans'), $key, new User());
    }

    public function testAnInvalidDraftWritesNothingAndKeepsTheGeneration(): void
    {
        $key = new AiKey('CODE', 5);
        $writer = $this->createMock(QuizWriter::class);
        $writer->expects($this->never())->method('create');
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->never())->method('transactional');

        try {
            $this->generateQuiz(['title' => 'X'] + self::DRAFT, $writer, $unitOfWork)->generate(new GenerateQuizInput(topic: 'volcans'), $key, new User());
            $this->fail('Un titre trop court aurait dû être refusé.');
        } catch (AiGenerationException) {
            $this->assertSame(5, $key->getRemainingGenerations());
        }
    }

    private function generateQuiz(array $draft, QuizWriter $writer, UnitOfWork $unitOfWork): GenerateQuiz
    {
        $generator = $this->createStub(AiQuizGenerator::class);
        $generator->method('generate')->willReturn($draft);

        return new GenerateQuiz($generator, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(), $writer, $unitOfWork);
    }
}
