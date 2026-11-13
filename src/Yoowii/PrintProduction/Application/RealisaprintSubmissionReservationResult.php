<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierSubmission;

/** Explicit authorization for one irreversible supplier request. */
final readonly class RealisaprintSubmissionReservationResult
{
    public function __construct(private PrintJobSupplierSubmission $submission, private ?string $attemptToken)
    {
    }

    public function submission(): PrintJobSupplierSubmission
    {
        return $this->submission;
    }

    public function acquired(): bool
    {
        return null !== $this->attemptToken;
    }

    public function attemptToken(): ?string
    {
        return $this->attemptToken;
    }
}
