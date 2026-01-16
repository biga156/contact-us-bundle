<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Unit\Validator;

use Caeligo\ContactUsBundle\Validator\HoneypotValidator;
use Caeligo\ContactUsBundle\Validator\Honeypot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class HoneypotValidatorTest extends TestCase
{
    private HoneypotValidator $validator;
    private ExecutionContextInterface $context;
    private ConstraintViolationBuilderInterface $violationBuilder;

    protected function setUp(): void
    {
        $this->validator = new HoneypotValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        
        $this->validator->initialize($this->context);
    }

    public function testEmptyValueIsValid(): void
    {
        $constraint = new Honeypot();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate('', $constraint);
    }

    public function testNullValueIsValid(): void
    {
        $constraint = new Honeypot();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate(null, $constraint);
    }

    public function testFilledHoneypotAddsViolation(): void
    {
        $constraint = new Honeypot();

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->violationBuilder);

        $this->violationBuilder->expects($this->once())
            ->method('addViolation');

        $this->validator->validate('bot@spam.com', $constraint);
    }

    public function testWhitespaceOnlyIsConsideredFilled(): void
    {
        $constraint = new Honeypot();

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->violationBuilder);

        $this->violationBuilder->expects($this->once())
            ->method('addViolation');

        $this->validator->validate('   ', $constraint);
    }

    public function testAnyNonEmptyValueTriggersViolation(): void
    {
        $constraint = new Honeypot();
        $testValues = ['spam', '0', 'false', '1', 'true', 'test@example.com'];

        $this->context->expects($this->exactly(count($testValues)))
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->violationBuilder);

        $this->violationBuilder->expects($this->exactly(count($testValues)))
            ->method('addViolation');

        foreach ($testValues as $value) {
            $this->validator->validate($value, $constraint);
        }
    }
}
