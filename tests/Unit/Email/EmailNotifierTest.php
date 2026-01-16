<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Unit\Email;

use Caeligo\ContactUsBundle\Email\EmailNotifier;
use Caeligo\ContactUsBundle\Entity\ContactMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailNotifierTest extends TestCase
{
    private MailerInterface $mailer;
    private Environment $twig;
    private EmailNotifier $notifier;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = $this->createMock(Environment::class);
        
        $config = [
            'enabled' => true,
            'from' => 'noreply@example.com',
            'to' => 'admin@example.com',
            'subject_prefix' => '[Contact]',
        ];

        $this->notifier = new EmailNotifier(
            $this->mailer,
            $this->twig,
            '@ContactUs/email/notification.html.twig',
            $config
        );
    }

    public function testSendNotificationWhenEnabled(): void
    {
        $message = new ContactMessage();
        $message->setName('John Doe');
        $message->setEmail('john@example.com');
        $message->setSubject('Test Subject');
        $message->setMessage('Test message content');

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                '@ContactUs/email/notification.html.twig',
                ['message' => $message]
            )
            ->willReturn('<html>Rendered email content</html>');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                return $email instanceof Email
                    && $email->getFrom()[0]->getAddress() === 'noreply@example.com'
                    && $email->getTo()[0]->getAddress() === 'admin@example.com'
                    && str_contains($email->getSubject(), '[Contact]')
                    && str_contains($email->getSubject(), 'Test Subject');
            }));

        $this->notifier->sendNotification($message);
    }

    public function testDoesNotSendWhenDisabled(): void
    {
        $config = [
            'enabled' => false,
            'from' => 'noreply@example.com',
            'to' => 'admin@example.com',
            'subject_prefix' => '[Contact]',
        ];

        $notifier = new EmailNotifier(
            $this->mailer,
            $this->twig,
            '@ContactUs/email/notification.html.twig',
            $config
        );

        $message = new ContactMessage();
        $message->setName('John Doe');
        $message->setEmail('john@example.com');

        $this->mailer->expects($this->never())
            ->method('send');

        $notifier->sendNotification($message);
    }

    public function testEmailContainsCorrectContent(): void
    {
        $message = new ContactMessage();
        $message->setName('Jane Smith');
        $message->setEmail('jane@example.com');
        $message->setSubject('Urgent Question');
        $message->setMessage('I need help with your product.');

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                '@ContactUs/email/notification.html.twig',
                $this->callback(function ($context) use ($message) {
                    return isset($context['message']) 
                        && $context['message'] === $message;
                })
            )
            ->willReturn('<html>Email content</html>');

        $capturedEmail = null;
        $this->mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function ($email) use (&$capturedEmail) {
                $capturedEmail = $email;
            });

        $this->notifier->sendNotification($message);

        $this->assertInstanceOf(Email::class, $capturedEmail);
        $this->assertEquals('[Contact] Urgent Question', $capturedEmail->getSubject());
        $this->assertEquals('jane@example.com', $capturedEmail->getReplyTo()[0]->getAddress());
    }

    public function testCustomSubjectPrefix(): void
    {
        $config = [
            'enabled' => true,
            'from' => 'noreply@example.com',
            'to' => 'admin@example.com',
            'subject_prefix' => '[CUSTOM PREFIX]',
        ];

        $notifier = new EmailNotifier(
            $this->mailer,
            $this->twig,
            '@ContactUs/email/notification.html.twig',
            $config
        );

        $message = new ContactMessage();
        $message->setName('John Doe');
        $message->setEmail('john@example.com');
        $message->setSubject('Test');
        $message->setMessage('Message');

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>Content</html>');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                return str_starts_with($email->getSubject(), '[CUSTOM PREFIX]');
            }));

        $notifier->sendNotification($message);
    }

    public function testEmailHasReplyToAddress(): void
    {
        $message = new ContactMessage();
        $message->setName('John Doe');
        $message->setEmail('john.reply@example.com');
        $message->setSubject('Test');
        $message->setMessage('Message');

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>Content</html>');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                $replyTo = $email->getReplyTo();
                return count($replyTo) === 1 
                    && $replyTo[0]->getAddress() === 'john.reply@example.com';
            }));

        $this->notifier->sendNotification($message);
    }
}
