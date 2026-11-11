<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Reports;

use App\Abstracts\Report;
use Illuminate\Support\Facades\DB;
use Modules\AkauntingReports\Support\CapitalSocialLedger;
use Modules\AkauntingReports\Support\CapitalSocialPolicy;
use Modules\AkauntingReports\Support\CapitalSocialSummary;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A native Akaunting report. Never performs an accounting mutation. */
final class CapitalSocial extends Report
{
    public $default_name = 'akaunting-reports::reports.capital_social';
    public $category = 'general.accounting';
    public $icon = 'account_balance';
    public $type = 'detail';
    public $chart = false;
    public $has_money = false;
    public $cacheable = false;

    /** @var list<array<string,mixed>> */
    public array $movements = [];
    /** @var array{members:list<array<string,mixed>>,total_cents:int,pending:int} */
    public array $capitalSummary = ['members' => [], 'total_cents' => 0, 'pending' => 0];
    public ?string $asOf = null;
    public ?int $selectedMember = null;
    public ?string $configurationError = null;

    public function setViews(): void
    {
        parent::setViews();
        $this->views['show'] = 'akaunting-reports::reports.capital-social.show';
        $this->views['print'] = 'akaunting-reports::reports.capital-social.print';
    }

    public function setTables(): void
    {
        $this->tables = [];
    }

    public function setDates(): void
    {
        // A stock balance is a point-in-time measure, not a monthly revenue series.
    }

    public function setData(): void
    {
        $companyId = (int) company_id();
        if ($companyId <= 0) {
            throw new \RuntimeException('An active company is required.');
        }
        $asOf = request()->query('as_of');
        if ($asOf !== null && !is_string($asOf)) {
            throw new \InvalidArgumentException('Invalid as-of date.');
        }
        $this->asOf = $asOf === '' ? null : $asOf;
        $memberId = request()->query('member_id');
        if ($memberId !== null && (!is_string($memberId) || !ctype_digit($memberId) || (int) $memberId <= 0)) {
            throw new \InvalidArgumentException('Invalid member identifier.');
        }
        $this->selectedMember = $memberId === null ? null : (int) $memberId;

        $settings = DB::table('settings')->where('company_id', $companyId)
            ->whereIn('key', [
                'akaunting-reports.capital.category_id',
                'akaunting-reports.capital.item_id',
                'akaunting-reports.capital.refund_code',
                'akaunting-reports.capital.advance_category_id',
                'akaunting-reports.capital.advance_item_id',
                'akaunting-reports.capital.legacy_refund_category_id',
                'akaunting-reports.capital.late_deduction_item_id',
                'akaunting-reports.capital.excluded_expense_category_id',
            ])->pluck('value', 'key');
        $categoryId = (int) ($settings['akaunting-reports.capital.category_id'] ?? 0);
        $itemId = (int) ($settings['akaunting-reports.capital.item_id'] ?? 0);
        if ($categoryId <= 0 || $itemId <= 0) {
            $this->configurationError = 'O relatório ainda não foi configurado para esta empresa.';
            return;
        }
        $policy = new CapitalSocialPolicy(
            companyId: $companyId,
            categoryId: $categoryId,
            itemId: $itemId,
            refundCategoryCode: (string) ($settings['akaunting-reports.capital.refund_code'] ?? 'capital-social-refund'),
            advanceCategoryId: self::positiveOrNull($settings['akaunting-reports.capital.advance_category_id'] ?? null),
            advanceItemId: self::positiveOrNull($settings['akaunting-reports.capital.advance_item_id'] ?? null),
            legacyRefundCategoryId: self::positiveOrNull($settings['akaunting-reports.capital.legacy_refund_category_id'] ?? null),
            lateDeductionItemId: self::positiveOrNull($settings['akaunting-reports.capital.late_deduction_item_id'] ?? null),
            excludedExpenseCategoryId: self::positiveOrNull($settings['akaunting-reports.capital.excluded_expense_category_id'] ?? null),
        );
        $this->movements = app(CapitalSocialLedger::class)->rows($policy, $this->selectedMember, $this->asOf);
        $this->capitalSummary = CapitalSocialSummary::calculate($this->movements);
    }

    private static function positiveOrNull(mixed $value): ?int
    {
        return $value !== null && (int) $value > 0 ? (int) $value : null;
    }

    public function show()
    {
        return view($this->views['show'], ['print' => false])->with('class', $this);
    }

    public function print()
    {
        return view($this->views['print'], ['print' => true])->with('class', $this);
    }

    public function pdf()
    {
        $html = view($this->views['print'], ['print' => true])->with('class', $this)->render();
        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper('A4', 'landscape');
        return $pdf->download('capital-social.pdf');
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                throw new \RuntimeException('Cannot open export stream.');
            }
            fputcsv($out, ['Date', 'Member', 'Movement', 'Amount (BRL)', 'Status', 'Document type', 'Document ID', 'Review reason']);
            foreach ($this->movements as $row) {
                fputcsv($out, [
                    (string) $row['date'],
                    self::safeCsv((string) $row['name']),
                    (string) $row['kind'],
                    number_format((int) $row['amount_cents'] / 100, 2, '.', ''),
                    (string) $row['status'],
                    (string) $row['source'],
                    (int) $row['id'],
                    self::safeCsv((string) $row['reason']),
                ]);
            }
            fclose($out);
        }, 'capital-social.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function safeCsv(string $value): string
    {
        return preg_match('/^[=+@\-\t\r]/u', $value) ? "'" . $value : $value;
    }
}
