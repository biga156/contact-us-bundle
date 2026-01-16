<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Unit\Form;

use Caeligo\ContactUsBundle\Entity\ContactMessage;
use Caeligo\ContactUsBundle\Form\ContactFormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class ContactFormTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidData(): void
    {
        $formData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Test Subject',
            'message' => 'This is a test message.',
        ];

        $model = new ContactMessage();
        $form = $this->factory->create(ContactFormType::class, $model);

        $expected = new ContactMessage();
        $expected->setName($formData['name']);
        $expected->setEmail($formData['email']);
        $expected->setSubject($formData['subject']);
        $expected->setMessage($formData['message']);

        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertEquals($expected->getName(), $model->getName());
        $this->assertEquals($expected->getEmail(), $model->getEmail());
        $this->assertEquals($expected->getSubject(), $model->getSubject());
        $this->assertEquals($expected->getMessage(), $model->getMessage());
    }

    public function testFormFields(): void
    {
        $form = $this->factory->create(ContactFormType::class);

        $this->assertTrue($form->has('name'));
        $this->assertTrue($form->has('email'));
        $this->assertTrue($form->has('subject'));
        $this->assertTrue($form->has('message'));
        $this->assertTrue($form->has('_form_token_time'));
    }

    public function testNameFieldIsRequired(): void
    {
        $formData = [
            'name' => '',
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => 'Test message',
        ];

        $form = $this->factory->create(ContactFormType::class);
        $form->submit($formData);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('name')->getErrors());
    }

    public function testEmailFieldValidation(): void
    {
        $formData = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'subject' => 'Test',
            'message' => 'Test message',
        ];

        $form = $this->factory->create(ContactFormType::class);
        $form->submit($formData);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('email')->getErrors());
    }

    public function testMessageFieldMinLength(): void
    {
        $formData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => 'Too short',
        ];

        $form = $this->factory->create(ContactFormType::class);
        $form->submit($formData);

        $this->assertFalse($form->isValid());
        $errors = $form->get('message')->getErrors();
        $this->assertGreaterThan(0, count($errors));
    }

    public function testHoneypotFieldIsOptional(): void
    {
        $formData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Test',
            'message' => 'This is a valid test message with sufficient length.',
        ];

        $form = $this->factory->create(ContactFormType::class);
        $form->submit($formData);

        $this->assertTrue($form->isValid());
    }
}
