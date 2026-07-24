<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Command;

use Jenssegers\Optimus\Energon;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tokenable:generate',
    description: 'Generate a fresh prime/inverse/random triplet for a #[Tokenable] entity.',
)]
final readonly class GenerateTokenableCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Prefix for the new Tokenable attribute (e.g. "fest").')]
        ?string $prefix = null,
    ): int {
        if (!class_exists(Energon::class) || !class_exists(\phpseclib\Math\BigInteger::class)) {
            $io->error('Triplet generation requires phpseclib/phpseclib (install via composer require --dev phpseclib/phpseclib:^2.0).');

            return Command::FAILURE;
        }

        /** @var array{0: int, 1: int, 2: int} $triplet */
        $triplet = Energon::generate();
        [$prime, $inverse, $random] = $triplet;

        $io->success('Triplet generated.');
        $io->writeln(\sprintf(
            "#[Tokenable(prefix: '%s', prime: %d, inverse: %d, random: %d)]",
            $prefix ?? 'change-me',
            $prime,
            $inverse,
            $random,
        ));

        return Command::SUCCESS;
    }
}
