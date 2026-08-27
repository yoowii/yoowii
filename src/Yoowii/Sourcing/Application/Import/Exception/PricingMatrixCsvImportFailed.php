<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application\Import\Exception;

final class PricingMatrixCsvImportFailed extends \DomainException
{
    /** @param non-empty-list<string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(sprintf(
            'The pricing matrix CSV import failed with %d error(s).',
            count($this->errors),
        ));
    }

    /** @return non-empty-list<string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
