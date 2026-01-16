<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Unit\Validator;

use Caeligo\ContactUsBundle\Validator\TimingValidator;
use Caeligo\ContactUsBundle\Validator\Timing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class TimingValidatorTest extends TestCase
{
    private TimingValidator $validator;
    private ExecutionContextInterface $context;
    private ConstraintViolationBuilderInterface $violationBuilder;

    protected function setUp(): void
    {
        $this->validator = new TimingValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        
        $this->validator->initialize($this->context);
    }

    public function testValidTimingDoesNotAddViolation(): void
    {
        $constraint = new Timing(['minSeconds' => 3]);
        $timestamp = time() - 5; // 5 seconds ago

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate($timestamp, $constraint);
    }

    public function testTooFastSubmissionAddsViolation(): void
    {
        $constraint = new Timing(['minSeconds' => 3]);
        $timestamp = time() - 1; // Only 1 second ago

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->violationBuilder);

        $this->violationBuilder->expects($this->once())
            ->method('setParameter')
            ->with('{{ seconds }}', '3')
            ->willReturnSelf();

        $this->violationBuilder->expects($this->once())
            ->method('addViolation');

        $this->validator->validate($timestamp, $constraint);
    }

    public function testNullValueIsValid(): void
    {
        $constraint = new Timing();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate(null, $constraint);
    }

    public function testEmptyStringIsValid(): void
    {
        $constraint = new Timing();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate('', $constraint);
    }

    public function testCustomMinimumSeconds(): void
    {
        $constraint = new Timing(['minSeconds' => 10]);
        $timestamp = time() - 5; // Only 5 seconds ago, but minimum is 10

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->violationBuilder);

        $this->violationBuilder->expects($this->once())
            ->method('setParameter')
            ->with('{{ seconds }}', '10')
            ->willReturnSelf();

        $this->violationBuilder->expects($this->once())
            ->method('addViolation');

        $this->validator->validate($timestamp, $constraint);
    }

    public function testExactMinimumTimeIsValid(): void
    {
        $constraint = new Timing(['minSeconds' => 3]);
        $timestamp = time() - 3; // Exactly 3 seconds ago

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate($timestamp, $constraint);
    }
}
