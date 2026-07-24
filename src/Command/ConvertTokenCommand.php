<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Command;

use LauLamanApps\Tokenable\Exception\InvalidTokenException;
use LauLamanApps\Tokenable\Tokenizer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tokenable:convert',
    description: 'Interactively convert a token to its class + id, or an id to a token.',
)]
final readonly class ConvertTokenCommand
{
    public function __construct(
        private readonly Tokenizer $tokenizer
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'A token (e.g. per_3f9k2) or a positive integer id to convert directly; omit for interactive mode.')]
        ?string $token = null,
    ): int {
        // One-shot mode: an identifier was passed on the command line.
        if (null !== $token && '' !== trim($token)) {
            $this->convertIdentifier($io, trim($token));

            return Command::SUCCESS;
        }

        $separator = $this->tokenizer->getSeparator();

        $io->title('Tokenable converter');
        $io->writeln(sprintf(
            'Enter a <comment>token</comment> (e.g. <comment>per%s3f9k2</comment>) to decode it, or a positive <comment>integer</comment> to encode it.',
            $separator,
        ));
        $io->writeln('Submit an empty line to quit.');

        while (true) {
            $answer = $io->ask('Identifier');
            if (!\is_string($answer) || '' === trim($answer)) {
                return Command::SUCCESS;
            }

            $this->convertIdentifier($io, trim($answer));
        }
    }

    private function convertIdentifier(SymfonyStyle $io, string $identifier): void
    {
        $separator = $this->tokenizer->getSeparator();

        if (str_contains($identifier, $separator)) {
            $this->tokenToId($io, $identifier);

            return;
        }

        if (!ctype_digit($identifier)) {
            $io->error(sprintf(
                '"%s" is neither a token (it lacks the "%s" separator) nor a positive integer.',
                $identifier,
                $separator,
            ));

            return;
        }

        $this->idToToken($io, (int) $identifier);
    }

    private function tokenToId(SymfonyStyle $io, string $token): void
    {
        try {
            [$class, $id] = $this->tokenizer->decode($token);
        } catch (InvalidTokenException $exception) {
            $io->error($exception->getMessage());

            return;
        }

        $io->success('Token decoded.');
        $io->definitionList(
            ['Class' => $class],
            ['Token' => $token],
            ['Id' => (string) $id],
        );
    }

    private function idToToken(SymfonyStyle $io, int $id): void
    {
        $classes = array_keys($this->tokenizer->getRegistered());
        if ([] === $classes) {
            $io->error('No #[Tokenable] entities are registered.');

            return;
        }

        $question = new Question('Entity class');
        $question->setAutocompleterValues($classes);
        $question->setValidator(static function (mixed $answer) use ($classes): mixed {
            if (!in_array($answer, $classes, true)) {
                throw new \RuntimeException('Enter a registered #[Tokenable] FQCN (use tab / arrow keys to autocomplete).');
            }

            return $answer;
        });

        /** @var class-string $class */
        $class = $io->askQuestion($question);
        $token = $this->tokenizer->encode($class, $id);

        $io->success('Id encoded.');
        $io->definitionList(
            ['Class' => $class],
            ['Id' => (string) $id],
            ['Token' => $token],
        );
    }
}
