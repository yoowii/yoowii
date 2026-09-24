<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierSubmission;
use App\Yoowii\PrintProduction\Domain\PrintJobStatus;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;

final readonly class SynchronizeRealisaprintOrderStatus
{
    public function __construct(private RealisaprintClient $client, private RecordPrintJobActivity $activity) {}
    public function __invoke(PrintJobSupplierSubmission $submission): bool
    {
        if (!$this->client->isEnabled() || 'submitted' !== $submission->status()) { return false; }
        $job = $submission->printJob();
        $response = $this->client->post('get_order', ['id_order' => $submission->supplierOrderId()]);
        $status = $response['status'] ?? null;
        if (!is_scalar($status)) { return false; }
        $now = new \DateTimeImmutable();
        if (in_array((int) $status, [22, 26, 30], true) && PrintJobStatus::Blocked !== $job->status()) {
            $job->changeStatus(PrintJobStatus::Blocked, $now, 'Realisaprint : ' . (string) ($response['status_label'] ?? 'problème bloquant'));
        } elseif (7 === (int) $status && PrintJobStatus::InProduction === $job->status()) {
            $tracking = $response['tracking'] ?? null;
            $number = is_array($tracking) ? ($tracking['tracking_numbers'] ?? null) : null;
            $url = is_array($tracking) ? ($tracking['tracking_url'] ?? null) : null;
            if (is_string($number) && '' !== trim($number)) { $job->markShipped($number, is_string($url) ? $url : null, $now); }
        } elseif (31 === (int) $status && PrintJobStatus::Shipped === $job->status()) {
            $job->changeStatus(PrintJobStatus::Delivered, $now);
        } else { return false; }
        ($this->activity)($job, 'realisaprint_status_synced', 'system', ['supplier_status' => (int) $status, 'supplier_label' => $response['status_label'] ?? null]);
        return true;
    }
}
