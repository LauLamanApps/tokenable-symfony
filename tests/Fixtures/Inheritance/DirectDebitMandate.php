<?php

declare(strict_types=1);

namespace LauLamanApps\Tokenable\Tests\Fixtures\Inheritance;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class DirectDebitMandate extends AbstractMandate
{
}
