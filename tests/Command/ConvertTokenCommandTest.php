<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Command;

use LauLamanApps\Tokenable\Command\ConvertTokenCommand;
use LauLamanApps\Tokenable\Tests\CreatesTokenizer;
use LauLamanApps\Tokenable\Tests\Fixtures\FooEntity;
use LauLamanApps\Tokenable\Tokenizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConvertTokenCommandTest extends TestCase
{
    use CreatesTokenizer;

    public function testDecodesAToken(): void
    {
        $tokenizer = $this->createTokenizer();
        $token = $tokenizer->encode(FooEntity::class, 42);

        $tester = $this->tester($tokenizer);
        $tester->setInputs([$token, '']);
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('Token decoded', $output);
        self::assertStringContainsString(FooEntity::class, $output);
        self::assertStringContainsString('42', $output);
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testConvertsTokenPassedAsArgument(): void
    {
        $tokenizer = $this->createTokenizer();
        $token = $tokenizer->encode(FooEntity::class, 42);

        $tester = $this->tester($tokenizer);
        // No inputs set: one-shot mode must not prompt.
        $tester->execute(['token' => $token]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('Token decoded', $output);
        self::assertStringContainsString(FooEntity::class, $output);
        self::assertStringContainsString('42', $output);
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testEncodesAnId(): void
    {
        $tokenizer = $this->createTokenizer();
        $expectedToken = $tokenizer->encode(FooEntity::class, 42);

        $tester = $this->tester($tokenizer);
        $tester->setInputs(['42', FooEntity::class, '']);
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('Id encoded', $output);
        self::assertStringContainsString($expectedToken, $output);
    }

    public function testRejectsGarbageInput(): void
    {
        $tester = $this->tester($this->createTokenizer());
        $tester->setInputs(['not-a-number-or-token', '']);
        $tester->execute([]);

        self::assertStringContainsString('neither a token', $tester->getDisplay());
    }

    private function tester(Tokenizer $tokenizer): CommandTester
    {
        // Invokable command (no `extends Command`): wrap it in a Command via setCode,
        // exactly as the container does when it is registered.
        $command = (new Command('app:tokenable:convert'))->setCode(new ConvertTokenCommand($tokenizer));

        return new CommandTester($command);
    }
}
