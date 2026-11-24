<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\ValidationException;
use Grav\Framework\Form\Interfaces\FormInterface;
use Grav\Framework\Form\Traits\FormTrait;

/**
 * Class FormErrorEscapingTest
 *
 * The error of a form is HTML: the messages partial and the form plugin print
 * it without escaping, because ValidationException builds its message as HTML
 * and escapes the values it inserts. Any other exception has a plain text
 * message which can quote page folders, file names and other stored or
 * submitted values, so FormTrait has to escape it when turning it into the
 * error of the form.
 *
 * Naming convention: test{Method}_{issue}_{description}
 */
class FormErrorEscapingTest extends \PHPUnit\Framework\TestCase
{
    private const XSS = '<img src=q onerror=alert(document.domain)>';
    private const ESCAPED = '&lt;img src=q onerror=alert(document.domain)&gt;';

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $grav();
    }

    public function testSubmit_ExceptionMessageIsEscaped(): void
    {
        $form = $this->createForm(new RuntimeException("Cannot rename object 'box" . self::XSS . "/item'"));
        $form->submit(['title' => 'Hello']);

        self::assertFalse($form->isValid());
        self::assertSame('Cannot rename object &apos;box' . self::ESCAPED . '/item&apos;', $form->getError());
    }

    public function testSubmit_ValidationExceptionKeepsItsHtml(): void
    {
        $exception = (new ValidationException('', 400))->setMessages(['title' => ['Invalid input: ' . self::XSS]]);
        $form = $this->createForm($exception);
        $form->submit(['title' => 'Hello']);

        self::assertFalse($form->isValid());
        self::assertSame($exception->getMessage(), $form->getError());
        self::assertStringContainsString('<br/>Invalid input: ' . self::ESCAPED, (string)$form->getError());
    }

    public function testValidate_ExceptionMessageIsEscaped(): void
    {
        $form = $this->createForm(null, new RuntimeException('Unable to upload file ' . self::XSS));
        $form->submit(['title' => 'Hello']);

        self::assertFalse($form->isValid());
        self::assertSame('Unable to upload file ' . self::ESCAPED, $form->getError());
    }

    public function testJsonDecode_FieldNameIsEscaped(): void
    {
        $form = $this->createForm();

        $method = new ReflectionMethod($form, 'decodeData');
        $method->invoke($form, ['_json' => ['k' . self::XSS => '{']]);

        self::assertFalse($form->isValid());
        self::assertSame('Badly encoded JSON data (for k' . self::ESCAPED . ') was sent to the form', $form->getError());
    }

    /**
     * @param Exception|null $onSubmit   Thrown when the form is saved.
     * @param Exception|null $onValidate Thrown when the form is validated.
     * @return FormInterface
     */
    private function createForm(?Exception $onSubmit = null, ?Exception $onValidate = null): FormInterface
    {
        $blueprint = new Blueprint(null, ['form' => ['fields' => ['title' => ['type' => 'text']]]]);
        $blueprint->init();

        return new class($blueprint, $onSubmit, $onValidate) implements FormInterface {
            use FormTrait;

            /** @var Blueprint */
            private $testBlueprint;
            /** @var Exception|null */
            private $onSubmit;
            /** @var Exception|null */
            private $onValidate;

            public function __construct(Blueprint $blueprint, ?Exception $onSubmit, ?Exception $onValidate)
            {
                $this->testBlueprint = $blueprint;
                $this->onSubmit = $onSubmit;
                $this->onValidate = $onValidate;
                $this->submitted = false;
            }

            public function getBlueprint(): Blueprint
            {
                return $this->testBlueprint;
            }

            protected function validateData($data = null): void
            {
                if ($this->onValidate) {
                    throw $this->onValidate;
                }
            }

            protected function doSubmit(array $data, array $files): void
            {
                if ($this->onSubmit) {
                    throw $this->onSubmit;
                }
            }
        };
    }
}
