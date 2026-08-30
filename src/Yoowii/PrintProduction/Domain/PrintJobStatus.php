<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain;

enum PrintJobStatus: string
{
    case AwaitingFiles = 'awaiting_files';
    case FilesReceived = 'files_received';
    case BatPending = 'bat_pending';
    case BatReady = 'bat_ready';
    case BatApproved = 'bat_approved';
    case InProduction = 'in_production';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Blocked = 'blocked';
    case Cancelled = 'cancelled';
}
