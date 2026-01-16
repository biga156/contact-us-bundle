<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Unit\Twig;

use Caeligo\ContactUsBundle\Twig\ContactUsExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class ContactUsExtensionTest extends TestCase
{
    public function testGetFunctions(): void
    {
        $extension = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => true, 'load_stimulus_controller' => true],
            ['enabled' => 'auto', 'domain' => 'contact_us', 'fallback_locale' => 'en']
        );

        $functions = $extension->getFunctions();

        $this->assertCount(4, $functions);
        $this->assertContainsOnlyInstancesOf(TwigFunction::class, $functions);

        $functionNames = array_map(fn(TwigFunction $f) => $f->getName(), $functions);
        $this->assertContains('contact_us_base_template', $functionNames);
        $this->assertContains('contact_us_form_template', $functionNames);
        $this->assertContains('contact_us_load_default_styles', $functionNames);
        $this->assertContains('contact_us_load_stimulus', $functionNames);
    }

    public function testGetFilters(): void
    {
        $extension = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => true, 'load_stimulus_controller' => true],
            ['enabled' => 'auto', 'domain' => 'contact_us', 'fallback_locale' => 'en']
        );

        $filters = $extension->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(TwigFilter::class, $filters[0]);
        $this->assertEquals('contact_trans', $filters[0]->getName());
    }

    public function testBaseTemplateGetter(): void
    {
        $extension = new ContactUsExtension(
            ['base' => 'custom_base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => false, 'load_stimulus_controller' => false],
            ['enabled' => 'auto', 'domain' => 'contact_us', 'fallback_locale' => 'en']
        );

        $this->assertEquals('custom_base.html.twig', $extension->getBaseTemplate());
    }

    public function testFormTemplateGetter(): void
    {
        $extension = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'custom_form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => false, 'load_stimulus_controller' => false],
            ['enabled' => 'auto', 'domain' => 'contact_us', 'fallback_locale' => 'en']
        );

        $this->assertEquals('custom_form.html.twig', $extension->getFormTemplate());
    }

    public function testLoadDefaultStyles(): void
    {
        $extensionTrue = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => true, 'load_stimulus_controller' => false],
            ['enabled' => 'auto', 'domain' => 'contact_us', 'fallback_locale' => 'en']
        );

        $extensionFalse = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => false, 'load_stimulus_controller' => false],
            ['enabled' => 'auto', 'domain' => 'contact_us', 'fallback_locale' => 'en']
        );

        $this->assertTrue($extensionTrue->shouldLoadDefaultStyles());
        $this->assertFalse($extensionFalse->shouldLoadDefaultStyles());
    }

    public function testLoadStimulusController(): void
    {
        $extensionTrue = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => false, 'load_stimulus_controller' => true],
            ['enabled' => 'auto', 'domain' => 'contact_us', 'fallback_locale' => 'en']
        );

        $extensionFalse = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => false, 'load_stimulus_controller' => false],
            ['enabled' => 'auto', 'domain' => 'contact_us', 'fallback_locale' => 'en']
        );

        $this->assertTrue($extensionTrue->shouldLoadStimulusController());
        $this->assertFalse($extensionFalse->shouldLoadStimulusController());
    }

    public function testTranslateWithTranslator(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with('contact.title', [], 'contact_us')
            ->willReturn('Contact Us');

        $extension = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => true, 'load_stimulus_controller' => true],
            ['enabled' => 'true', 'domain' => 'contact_us', 'fallback_locale' => 'en'],
            $translator
        );

        $result = $extension->translate('contact.title');
        $this->assertEquals('Contact Us', $result);
    }

    public function testTranslateWithoutTranslatorFallsBackToPlainText(): void
    {
        $extension = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => true, 'load_stimulus_controller' => true],
            ['enabled' => 'false', 'domain' => 'contact_us', 'fallback_locale' => 'en']
        );

        $result = $extension->translate('contact.field.name');
        $this->assertEquals('Name', $result);

        $result = $extension->translate('contact.field.email_address');
        $this->assertEquals('Email Address', $result);

        $result = $extension->translate('your_custom_label');
        $this->assertEquals('Your Custom Label', $result);
    }

    public function testTranslateWithParameters(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with('contact.greeting', ['%name%' => 'John'], 'contact_us')
            ->willReturn('Hello John');

        $extension = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => true, 'load_stimulus_controller' => true],
            ['enabled' => 'true', 'domain' => 'contact_us', 'fallback_locale' => 'en'],
            $translator
        );

        $result = $extension->translate('contact.greeting', ['%name%' => 'John']);
        $this->assertEquals('Hello John', $result);
    }

    public function testTranslateWithCustomDomain(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with('key', [], 'custom_domain')
            ->willReturn('Translated');

        $extension = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => true, 'load_stimulus_controller' => true],
            ['enabled' => 'true', 'domain' => 'contact_us', 'fallback_locale' => 'en'],
            $translator
        );

        $result = $extension->translate('key', [], 'custom_domain');
        $this->assertEquals('Translated', $result);
    }

    public function testAutoDetectTranslatorEnabled(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->willReturn('Translated');

        $extension = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => true, 'load_stimulus_controller' => true],
            ['enabled' => 'auto', 'domain' => 'contact_us', 'fallback_locale' => 'en'],
            $translator
        );

        $extension->translate('test.key');
    }

    public function testAutoDetectTranslatorDisabled(): void
    {
        $extension = new ContactUsExtension(
            ['base' => 'base.html.twig', 'form' => 'form.html.twig', 'email' => 'email.html.twig'],
            ['load_default_styles' => true, 'load_stimulus_controller' => true],
            ['enabled' => 'auto', 'domain' => 'contact_us', 'fallback_locale' => 'en'],
            null // No translator
        );

        $result = $extension->translate('test.field.name');
        $this->assertEquals('Name', $result);
    }
}
