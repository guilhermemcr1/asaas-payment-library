<?php

declare(strict_types=1);

namespace AsaasBiblioteca\Mappers;

final class AsaasStatusMapper
{
    public function toInternalStatus(string $asaasStatus, string $eventType = ''): string
    {
        $status = strtolower(trim($asaasStatus));
        $event = strtolower(trim($eventType));

        $paidStatuses = ['received', 'confirmed', 'received_in_cash', 'anticipated'];
        $paidEvents = ['payment_received', 'payment_confirmed', 'payment_anticipated'];
        if (in_array($status, $paidStatuses, true) || in_array($event, $paidEvents, true)) {
            return 'Pago';
        }

        $cancelledStatuses = [
            'refunded',
            'refund_requested',
            'refund_in_progress',
            'refund_denied',
            'received_in_cash_undone',
            'chargeback_requested',
            'chargeback_dispute',
            'awaiting_chargeback_reversal',
            'deleting',
            'deleted',
        ];
        $cancelledEvents = [
            'payment_deleted',
            'payment_refunded',
            'payment_refund_in_progress',
            'payment_refund_denied',
            'payment_received_in_cash_undone',
            'payment_chargeback_requested',
            'payment_chargeback_dispute',
            'payment_awaiting_chargeback_reversal',
            'payment_bank_slip_cancelled',
            'payment_credit_card_capture_refused',
            'payment_partially_refunded',
        ];
        if (in_array($status, $cancelledStatuses, true) || in_array($event, $cancelledEvents, true)) {
            return 'Cancelado';
        }

        $overdueStatuses = ['overdue'];
        $overdueEvents = ['payment_overdue'];
        if (in_array($status, $overdueStatuses, true) || in_array($event, $overdueEvents, true)) {
            return 'Vencido';
        }

        return 'Pendente';
    }
}
