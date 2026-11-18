<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Enums\WorkshopOrderStatus;
use App\Models\Quote;
use App\Models\WorkshopOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class WorkshopOrderWorkflowManager
{
    public function prepareQuote(WorkshopOrder $order, ?string $diagnosis = null): Quote
    {
        return DB::transaction(function () use ($order, $diagnosis): Quote {
            $order = WorkshopOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status === WorkshopOrderStatus::Received) {
                if (filled($diagnosis)) {
                    $order->update(['diagnosis' => trim($diagnosis)]);
                }
                $order = $this->confirmDiagnosis($order);
            }

            if ($order->status !== WorkshopOrderStatus::Diagnosed) {
                throw new LogicException('Le dossier doit être au diagnostic avant de préparer le devis.');
            }

            $quote = $order->quote()->first();
            if ($quote !== null && $quote->status !== QuoteStatus::Draft) {
                throw new LogicException('Le devis de ce dossier a déjà été traité.');
            }

            return $quote ?? app(WorkshopOrderQuoteManager::class)->sync($order);
        }, 3);
    }

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
            if (! in_array($order->status, [WorkshopOrderStatus::Diagnosed, WorkshopOrderStatus::AwaitingApproval, WorkshopOrderStatus::Approved], true)) {
                throw new LogicException('Le dossier doit avoir un diagnostic validé et, s’il existe, un devis traité avant sa planification.');
            }

            $order->update([
                'status' => WorkshopOrderStatus::Scheduled,
                'authorized_at' => $order->authorized_at ?? now(),
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
        return DB::transaction(function () use ($order): WorkshopOrder {
            $order = WorkshopOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, [WorkshopOrderStatus::Approved, WorkshopOrderStatus::Scheduled, WorkshopOrderStatus::InProgress], true)) {
                throw new LogicException('L’intervention doit être autorisée avant de déclarer l’instrument prêt.');
            }

            $order->update(['status' => WorkshopOrderStatus::Ready, 'ready_at' => now()]);

            return $order->fresh();
        });
    }

    public function setFollowUpDate(WorkshopOrder $order, string $field, ?string $date): void
    {
        $eligibleStatuses = match ($field) {
            'examination_on' => [WorkshopOrderStatus::Received],
            'quote_follow_up_on' => [WorkshopOrderStatus::Diagnosed],
            'work_due_on' => [WorkshopOrderStatus::Approved, WorkshopOrderStatus::Scheduled, WorkshopOrderStatus::InProgress],
        };

        DB::transaction(function () use ($order, $field, $date, $eligibleStatuses): void {
            $order = WorkshopOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, $eligibleStatuses, true)) {
                throw new LogicException('Ce rappel ne correspond plus à l’étape du dossier.');
            }
            if ($field === 'quote_follow_up_on' && $order->quote()->first()?->status !== QuoteStatus::Draft) {
                throw new LogicException('Ce dossier n’a pas de devis à transmettre.');
            }

            $order->update([$field => $date]);
        });
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

    public function markApproved(WorkshopOrder $order): WorkshopOrder
    {
        return DB::transaction(function () use ($order): WorkshopOrder {
            $order = WorkshopOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== WorkshopOrderStatus::AwaitingApproval) {
                throw new LogicException('Le dossier n’attend pas l’accord du client.');
            }
            $order->update(['status' => WorkshopOrderStatus::Approved, 'authorized_at' => now()]);

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
