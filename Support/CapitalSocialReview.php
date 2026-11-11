<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

/**
 * Presentation-only labels for financial classification.
 * This class never modifies records or considers a manual click sufficient
 * evidence of integralization.
 */
final class CapitalSocialReview
{
    private const UNPAID = 'Unpaid document: capital is not yet confirmed.';

    public static function category(array $row): string
    {
        if (($row['status'] ?? '') === 'confirmado') {
            return 'confirmed';
        }
        return ($row['reason'] ?? '') === self::UNPAID ? 'awaiting_payment' : 'requires_review';
    }

    public static function label(array $row): string
    {
        if (($row['status'] ?? '') === 'confirmado') {
            return empty($row['reason']) ? 'Confirmada' : 'Confirmada com ressalva';
        }

        return match ((string) ($row['reason'] ?? '')) {
            self::UNPAID => 'Aguardando pagamento',
            'Partially paid document: capital allocation needs review.' => 'Pagamento parcial',
            'Capital-related transaction references a non-capital document.' => 'Revisar vínculo documental',
            'Cancelled or voided document must be reconciled separately.' => 'Documento cancelado',
            'Payments exceed the document amount.' => 'Pagamentos divergentes',
            'Foreign-currency document requires verified conversion.' => 'Conversão de moeda pendente',
            'Currency or amount needs verification.' => 'Moeda ou valor a verificar',
            'Transaction has insufficient or ambiguous capital classification.' => 'Classificação de capital indefinida',
            'Unsupported document type.' => 'Tipo de documento não suportado',
            'Document contains ambiguous capital items or direction.' => 'Itens de capital inconsistentes',
            default => 'Conferência necessária',
        };
    }

    public static function guidance(array $row): string
    {
        if (($row['status'] ?? '') === 'confirmado') {
            return empty($row['reason'])
                ? 'Movimentação confirmada pelas regras financeiras atuais.'
                : 'O saldo foi confirmado, mas há uma ressalva documental a examinar.';
        }

        return match ((string) ($row['reason'] ?? '')) {
            self::UNPAID => 'Não requer aprovação no relatório. Após registrar e conciliar o pagamento no Akaunting, a situação será recalculada.',
            'Partially paid document: capital allocation needs review.' => 'Confira a alocação do pagamento parcial. A integralização não pode ser presumida.',
            'Capital-related transaction references a non-capital document.' => 'Confira o vínculo da transação com o documento e sua classificação de capital social.',
            'Cancelled or voided document must be reconciled separately.' => 'Documento cancelado: verifique a necessidade de estorno ou registro de devolução.',
            'Payments exceed the document amount.' => 'Confira pagamentos duplicados, adiantamentos e eventuais deduções posteriores.',
            'Foreign-currency document requires verified conversion.', 'Currency or amount needs verification.' => 'Verifique moeda, conversão e valor na origem antes de reconhecer o capital.',
            default => 'Abra a origem e corrija as informações contábeis, se necessário. O relatório recalcula automaticamente.',
        };
    }

    /** @param list<array<string,mixed>> $movements */
    public static function counts(array $movements): array
    {
        $counts = ['confirmed' => 0, 'awaiting_payment' => 0, 'requires_review' => 0];
        foreach ($movements as $row) {
            ++$counts[self::category($row)];
        }
        return $counts;
    }
}
