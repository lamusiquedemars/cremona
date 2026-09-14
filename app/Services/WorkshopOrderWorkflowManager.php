<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Enums\WorkshopOrderStatus;
use App\Models\WorkshopOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class WorkshopOrderWorkflowManager
{
    public function confirmDiagnosis(WorkshopOrder $order): WorkshopOrder
    {
        return DB::transaction(function () use ($order): WorkshopOrder {
            $order = WorkshopOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (blank($order->diagnosis)) {
                throw new LogicException('Renseignez et enregistrez d’abord le diagnostic atelier.');
            }
            if ($order->status !== WorkshopOrderStatus::Received) {
                throw new LogicException('Seul un dossier reçu peut être validé au diagnostic.');
            }

            $order->update(['status' => WorkshopOrderStatus::Diagnosed, 'diagnosed_at' => now()]);

            return $order->fresh();
        });
    }

    public function schedule(WorkshopOrder $order, Carbon $dueAt, ?string $authorizationNote = null): WorkshopOrder
    {
        return DB::transaction(function () use ($order, $dueAt, $authorizationNote): WorkshopOrder {
            $order = WorkshopOrder::query()->lockForUpdate()->with('quote')->findOrFail($order->id);
            $quote = $order->quote;

            if ($quote !== null && $quote->status !== QuoteStatus::Accepted) {
                throw new LogicException('Le devis lié doit être accepté avant de planifier l’intervention.');
            }
            if ($quote === null && blank($authorizationNote)) {
                throw new LogicException('Sans devis, indiquez le motif de l’accord avant de planifier.');
            }
            if (! in_array($order->status, [WorkshopOrderStatus::Diagnosed, WorkshopOrderStatus::AwaitingApproval], true)) {
                throw new LogicException('Le dossier doit avoir un diagnostic validé et, s’il existe, un devis traité avant sa planification.');
            }

            $order->update([
                'status' => WorkshopOrderStatus::Scheduled,
                'authorized_at' => now(),
                'authorization_note' => filled($authorizationNote) ? trim($authorizationNote) : $order->authorization_note,
                'due_at' => $dueAt,
                'scheduled_at' => now(),
            ]);

            return $order->fresh();
        });
    }

    public function start(WorkshopOrder $order): WorkshopOrder
    {
        return $this->transition($order, WorkshopOrderStatus::Scheduled, WorkshopOrderStatus::InProgress, ['started_at' => now()]);
    }

    public function markReady(WorkshopOrder $order): WorkshopOrder
    {
        return $this->transition($order, WorkshopOrderStatus::InProgress, WorkshopOrderStatus::Ready, ['ready_at' => now()]);
    }

    public function markReturned(WorkshopOrder $order): WorkshopOrder
    {
        return $this->transition($order, WorkshopOrderStatus::Ready, WorkshopOrderStatus::Returned, ['returned_at' => now()]);
    }

    public function markAwaitingApproval(WorkshopOrder $order): WorkshopOrder
    {
        return DB::transaction(function () use ($order): WorkshopOrder {
            $order = WorkshopOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== WorkshopOrderStatus::Diagnosed) {
                throw new LogicException('Le dossier doit avoir un diagnostic validé avant l’envoi du devis.');
            }

            $order->update(['status' => WorkshopOrderStatus::AwaitingApproval]);

            return $order->fresh();
        });
    }

    private function transition(WorkshopOrder $order, WorkshopOrderStatus $from, WorkshopOrderStatus $to, array $attributes): WorkshopOrder
    {
        return DB::transaction(function () use ($order, $from, $to, $attributes): WorkshopOrder {
            $order = WorkshopOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== $from) {
                throw new LogicException("Cette action est disponible uniquement lorsque le dossier est « {$from->getLabel()} ».");
            }

            $order->update(['status' => $to, ...$attributes]);

            return $order->fresh();
        });
    }
}
