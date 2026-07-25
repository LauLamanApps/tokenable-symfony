<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures\Inheritance;

use Doctrine\ORM\Mapping as ORM;
use LauLamanApps\Tokenable\Attribute\Tokenable;

#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    'direct_debit' => DirectDebitMandate::class,
    'credit_card' => CreditCardMandate::class,
    'bank_transfer' => BankTransferMandate::class,
])]
#[Tokenable(prefix: 'mnd', prime: 1580030173, inverse: 59260789, random: 1163945558)]
abstract class AbstractMandate
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        #[ORM\GeneratedValue]
        private readonly int $id = 1,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
