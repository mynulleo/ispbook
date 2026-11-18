<?php

namespace App\Traits;

use DateTime;
use App\Models\Client;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Employee;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\ExpenseDetail;
use App\Models\PaymentDetail;
use App\Models\VoucherDetail;
use App\Models\Commission;
use App\Models\UplinkProvider;
use Illuminate\Support\Carbon;
use App\Models\BandwidthHistory;
use App\Models\SalarySheetDetail;
use Illuminate\Support\Facades\DB;

trait ReportTrait
{

    public function getOpeningBalance($account_id, $from_date)
    {
        $opening_balance = 0;

        // get account (ignore soft delete)
        $account = Account::whereNull('deleted_at')->find($account_id);

        if (!$account) {
            return 0;
        }

        // base opening balance from account table
        $opening_balance = 0;

        // total debit before date (ignore soft delete)
        $total_debit = VoucherDetail::join('vouchers', function ($join) use ($from_date) {
            $join->on('vouchers.id', '=', 'voucher_details.voucher_id')
                ->whereNull('vouchers.deleted_at')
                ->whereDate('vouchers.voucher_date', '<', $from_date);
        })
            ->where('voucher_details.account_id', $account_id)
            ->whereNull('voucher_details.deleted_at')
            ->sum('voucher_details.dr_amount');

        // total credit before date (ignore soft delete)
        $total_credit = VoucherDetail::join('vouchers', function ($join) use ($from_date) {
            $join->on('vouchers.id', '=', 'voucher_details.voucher_id')
                ->whereNull('vouchers.deleted_at')
                ->whereDate('vouchers.voucher_date', '<', $from_date);
        })
            ->where('voucher_details.account_id', $account_id)
            ->whereNull('voucher_details.deleted_at')
            ->sum('voucher_details.cr_amount');

        // apply accounting rule based on account type
        if (in_array($account->account_type, ['Liability', 'Capital', 'Income'])) {

            // Liability type formula
            $opening_balance = $opening_balance + $total_credit - $total_debit;
        } else {

            // Asset / Expense type formula
            $opening_balance = $opening_balance + $total_debit - $total_credit;
        }

        return (float) $opening_balance;
    }

    public function getItemLadger($itemid, $fromDate = null, $toDate = null)
    {

        if (!$itemid) {
            return response()->json([
                'status'  => false,
                'message' => 'Item ID is required'
            ], 422);
        }

        // ----------------------------
        // CASE 1: Date Range Provided
        // ----------------------------
        if ($fromDate && $toDate) {

            /** 1️⃣ Opening Balance (before from_date) */
            $openingBalance = DB::table('stock_transactions')
                ->where('item_id', $itemid)
                ->where('status', 'active')
                ->where('transaction_date', '<', $fromDate)
                ->selectRaw('
                    COALESCE(SUM(qty_in), 0) - COALESCE(SUM(qty_out), 0)
                    AS opening_balance
                ')
                ->value('opening_balance');

            /** 2️⃣ Start running balance from opening */
            DB::statement("SET @balance := {$openingBalance}");

            /** 3️⃣ Ledger rows (from → to) */
            $ledger = DB::table('stock_transactions as st')
                ->leftJoin('purchases as p', function ($join) {
                    $join->on('p.id', '=', 'st.reference_id')
                        ->where('st.reference_type', 'Purchase');
                })
                ->leftJoin('issues as i', function ($join) {
                    $join->on('i.id', '=', 'st.reference_id')
                        ->where('st.reference_type', 'Issue');
                })
                ->select(
                    'st.transaction_date',
                    'st.transaction_type',
                    'st.reference_type',
                    DB::raw("
                        CASE
                            WHEN st.reference_type = 'Purchase' THEN p.invoiceno
                            WHEN st.reference_type = 'Issue' THEN i.issueno
                            ELSE NULL
                        END AS reference_no
                    "),
                    'st.qty_in',
                    'st.qty_out',
                    DB::raw('(@balance := @balance + st.qty_in - st.qty_out) AS balance')
                )
                ->where('st.item_id', $itemid)
                ->whereBetween('st.transaction_date', [$fromDate, $toDate])
                ->where('st.status', 'active')
                ->orderBy('st.transaction_date')
                ->orderBy('st.id')
                ->get();

            /** 4️⃣ Opening row add (structure unchanged) */
            $openingRow = [
                'transaction_date' => $fromDate,
                'transaction_type' => 'Opening Balance',
                'reference_type'   => null,
                'reference_no'     => null,
                'qty_in'           => 0,
                'qty_out'          => 0,
                'balance'          => $openingBalance
            ];

            $ledger->prepend($openingRow);

            return $ledger;
        }

        // ----------------------------
        // CASE 2: Only Item Ledger
        // ----------------------------
        DB::statement('SET @balance := 0');

        $ledger = DB::table('stock_transactions as st')
            ->leftJoin('purchases as p', function ($join) {
                $join->on('p.id', '=', 'st.reference_id')
                    ->where('st.reference_type', 'Purchase');
            })
            ->leftJoin('issues as i', function ($join) {
                $join->on('i.id', '=', 'st.reference_id')
                    ->where('st.reference_type', 'Issue');
            })
            ->select(
                'st.transaction_date',
                'st.transaction_type',
                'st.reference_type',
                DB::raw("
                    CASE
                        WHEN st.reference_type = 'Purchase' THEN p.invoiceno
                        WHEN st.reference_type = 'Issue' THEN i.issueno
                        ELSE NULL
                    END AS reference_no
                "),
                'st.qty_in',
                'st.qty_out',
                DB::raw('(@balance := @balance + st.qty_in - st.qty_out) AS balance')
            )
            ->where('st.item_id', $itemid)
            ->where('st.status', 'active')
            ->orderBy('st.transaction_date')
            ->orderBy('st.id')
            ->get();

        return $ledger;
    }

    public function getIncomeStatement($searchdata)
    {
        $from = array_key_exists('from_date', $searchdata)
            ? vue_to_server_date($searchdata['from_date'])
            : date('Y-m-01');

        $to = array_key_exists('to_date', $searchdata)
            ? vue_to_server_date($searchdata['to_date'])
            : date('Y-m-t');

        $total_income = VoucherDetail::whereNull('deleted_at')
            ->where('status', 'active')
            ->whereHas('account', function ($q) {
                $q->where('account_type', 'Income')
                    ->where('status', 'active');
            })
            ->whereHas('voucher', function ($q) use ($from, $to) {
                $q->whereBetween('voucher_date', [$from, $to])
                    ->whereNull('deleted_at')
                    ->where('status', 'active');
            })
            ->sum('cr_amount');

        $total_expense = VoucherDetail::whereNull('deleted_at')
            ->where('status', 'active')
            ->whereHas('account', function ($q) {
                $q->where('account_type', 'Expense')
                    ->where('status', 'active');
            })
            ->whereHas('voucher', function ($q) use ($from, $to) {
                $q->whereBetween('voucher_date', [$from, $to])
                    ->whereNull('deleted_at')
                    ->where('status', 'active');
            })
            ->sum('dr_amount');

        $net_profit = $total_income - $total_expense;

        $details = VoucherDetail::selectRaw('
            account_id,
            SUM(dr_amount) as total_debit,
            SUM(cr_amount) as total_credit
        ')
            ->with(['account:id,account_code,account_name,account_type'])
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->whereHas('account', function ($q) {
                $q->whereIn('account_type', ['Income', 'Expense'])
                    ->where('status', 'active');
            })
            ->whereHas('voucher', function ($q) use ($from, $to) {
                $q->whereBetween('voucher_date', [$from, $to])
                    ->whereNull('deleted_at')
                    ->where('status', 'active');
            })
            ->groupBy('account_id')
            ->get();


        return response()->json([
            'from' => $from,
            'to' => $to,
            'total_income' => (float) $total_income,
            'total_expense' => (float) $total_expense,
            'net_profit' => (float) $net_profit,
            'details' => $details,
        ]);
    }

    public function getExpenseStatement($searchdata)
    {
        $query = ExpenseDetail::with([
            'expense:id,expenseid,expense_date,branch_id,employee_id,approved_by,approved_date',
            'expense.branch:id,branch_name',
            'expense.approved_admin:id,name',
            'expense.employee:id,full_name',
            'account:id,account_code,account_name'
        ])
            ->whereNull('expense_details.deleted_at')

            // 🔹 Paid Amount per Expense Detail
            ->addSelect([
                'paid_amount' => DB::table('payment_details as pd')
                    ->whereColumn('pd.reference_id', 'expense_details.id')
                    ->where('pd.reference_type', 'ExpenseDetail')
                    ->whereNull('pd.deleted_at')
                    ->selectRaw('COALESCE(SUM(pd.amount),0)')
            ]);

        // 🔹 Only Approved Expense
        $query->whereHas('expense', function ($q) {
            $q->whereNotNull('approved_by')
                ->whereNull('deleted_at');
        });

        // 🔹 Account Filter
        if (!empty($searchdata['account_id'])) {
            $query->whereHas('account', function ($q) use ($searchdata) {
                $q->where('id', $searchdata['account_id']);
            });
        }

        // 🔹 Employee Filter
        if (!empty($searchdata['employee_id'])) {
            $query->whereHas('expense', function ($q) use ($searchdata) {
                $q->where('employee_id', $searchdata['employee_id']);
            });
        }

        // 🔹 Branch Filter
        if (!empty($searchdata['branch_id'])) {
            $query->whereHas('expense', function ($q) use ($searchdata) {
                $q->where('branch_id', $searchdata['branch_id']);
            });
        }

        // 🔹 Date Range Filter (Optional)
        if (!empty($searchdata['from_date']) && !empty($searchdata['to_date'])) {

            $from = vue_to_server_date($searchdata['from_date']);
            $to   = vue_to_server_date($searchdata['to_date']);

            $query->whereHas('expense', function ($q) use ($from, $to) {
                $q->whereBetween('expense_date', [$from, $to]);
            });
        }

        $details = $query->get();

        // 🔹 Add Due Amount Manually
        $details->transform(function ($item) {
            $item->due_amount = $item->amount - $item->paid_amount;
            return $item;
        });

        // 🔹 Totals
        $total_expense = $details->sum('amount');
        $total_paid    = $details->sum('paid_amount');
        $total_due     = $details->sum('due_amount');

        return response()->json([
            'details'        => $details,
            'total_expense'  => $total_expense,
            'total_paid'     => $total_paid,
            'total_due'      => $total_due,
        ]);
    }

    public function getCashBook($searchdata)
    {
        // 1️⃣ Date range
        $from = $searchdata['from_date'] ?? date('Y-m-01');
        $from = vue_to_server_date($from);

        $to = $searchdata['to_date'] ?? date('Y-m-t');
        $to = vue_to_server_date($to);

        // 2️⃣ Cash account ID
        $cashAccountId = Account::where('system_key_name', 'Cash')
            ->whereNull('deleted_at')
            ->value('id');

        if (!$cashAccountId) {
            return response()->json(['message' => 'Cash account not found'], 400);
        }

        // 3️⃣ Opening balance (soft delete safe)
        $opening_balance = $this->getOpeningBalance($cashAccountId, $from);

        // 4️⃣ Records within date range (soft delete safe)
        $records = VoucherDetail::with([
            'voucher' => function ($q) use ($from, $to) {
                $q->whereNull('vouchers.deleted_at')      // ✅ table name explicit
                    ->where('vouchers.status', 'active')
                    ->whereBetween('vouchers.voucher_date', [$from, $to]);
            }
        ])
            ->where('voucher_details.account_id', $cashAccountId)
            ->whereNull('voucher_details.deleted_at')      // ✅ table name explicit
            ->where('voucher_details.status', 'active')    // ✅ table name explicit
            ->join('vouchers', function ($join) use ($from, $to) {
                $join->on('vouchers.id', '=', 'voucher_details.voucher_id')
                    ->whereNull('vouchers.deleted_at')        // ✅ table name explicit
                    ->where('vouchers.status', 'active')
                    ->whereBetween('vouchers.voucher_date', [$from, $to]);
            })
            ->orderBy('vouchers.voucher_date', 'asc')
            ->orderBy('voucher_details.id', 'asc')
            ->get();

        // 5️⃣ Totals inside period
        $total_in = $records->sum('dr_amount');
        $total_out = $records->sum('cr_amount');

        // 6️⃣ Running balance calculation
        $running_balance = $opening_balance;

        $records = $records->map(function ($row) use (&$running_balance) {

            $dr = (float) $row->dr_amount;
            $cr = (float) $row->cr_amount;

            $running_balance = $running_balance + $dr - $cr;

            return [
                'date' => $row->voucher->voucher_date ?? null,
                'description' => $row->line_narration ?? ($row->voucher->narration ?? ''),
                'cash_in' => $dr,
                'cash_out' => $cr,
                'balance' => round($running_balance, 2),
            ];
        });

        $closing_balance = $running_balance;

        return response()->json([
            'opening_date' => date('01 M, Y', strtotime($from)),
            'from' => $from,
            'to' => $to,
            'opening_balance' => round($opening_balance, 2),
            'closing_balance' => round($closing_balance, 2),
            'total_in' => round($total_in, 2),
            'total_out' => round($total_out, 2),
            'records' => $records,
        ]);
    }

    public function getLedger($searchdata)
    {
        // current month first & last date if not provided
        $from = array_key_exists('from_date', $searchdata)
            ? vue_to_server_date($searchdata['from_date'])
            : date('Y-m-01');

        $to   = array_key_exists('to_date', $searchdata)
            ? vue_to_server_date($searchdata['to_date'])
            : date('Y-m-t');

        $account_id = $searchdata['account_id'] ?? null;

        if (!$account_id) {
            return response()->json([
                'message' => 'Account not selected'
            ], 400);
        }

        $account = Account::whereNull('deleted_at')->find($account_id);

        // Opening balance before period (must ignore soft deleted)
        $opening_balance = $this->getOpeningBalance($account_id, $from);

        // Period transactions (ignore soft deleted voucher_details and vouchers)
        $entries = VoucherDetail::with([
            'voucher' => function ($q) {
                $q->whereNull('deleted_at');
            }
        ])
            ->where('voucher_details.account_id', $account_id)
            ->whereNull('voucher_details.deleted_at')
            ->join('vouchers', function ($join) use ($from, $to) {
                $join->on('vouchers.id', '=', 'voucher_details.voucher_id')
                    ->whereNull('vouchers.deleted_at')
                    ->whereBetween('vouchers.voucher_date', [$from, $to]);
            })
            ->orderBy('vouchers.voucher_date', 'asc')
            ->orderBy('voucher_details.id', 'asc')
            ->select('voucher_details.*')
            ->get();

        // Running balance calculation
        $runningBalance = (float) $opening_balance;

        $entries = $entries->map(function ($row) use (&$runningBalance, $account) {

            $debit  = (float) $row->dr_amount;
            $credit = (float) $row->cr_amount;

            // Account type wise balance rule
            if (in_array($account->account_type, ['Liability', 'Capital', 'Income'])) {
                $runningBalance = $runningBalance + $credit - $debit;
            } else {
                $runningBalance = $runningBalance + $debit - $credit;
            }

            $row->running_balance = $runningBalance;

            return $row;
        });

        return response()->json([

            // summary
            'opening_date'     => date('01 M, Y', strtotime($from)),
            'opening_balance'  => (float) $opening_balance,
            'total_debit'      => (float) $entries->sum('dr_amount'),
            'total_credit'     => (float) $entries->sum('cr_amount'),
            'closing_balance'  => (float) $runningBalance,

            // entries
            'entries'          => $entries,

            // account info
            'account_name'     => $account->account_name ?? '',
            'account_type'     => $account->account_type ?? ''
        ]);
    }

    public function getTrialBalance($searchdata)
    {
        $from = $searchdata['from_date'] ?? null;
        $from = $from ? vue_to_server_date($from) : null;

        $to   = $searchdata['to_date'] ?? null;
        $to   = $to ? vue_to_server_date($to) : null;

        // Get all active accounts (ignore soft deleted)
        $accounts = Account::whereNull('deleted_at')
            ->orderBy('account_type')
            ->get()
            ->map(function ($account) use ($from, $to) {

                // Base query for VoucherDetail (soft delete safe)
                $vdQuery = VoucherDetail::where('account_id', $account->id)
                    ->whereNull('voucher_details.deleted_at')
                    ->where('voucher_details.status', 'active')
                    ->whereHas('voucher', function ($q) use ($from, $to) {
                        $q->whereNull('vouchers.deleted_at')
                            ->where('vouchers.status', 'active');

                        if ($from && $to) {
                            $q->whereBetween('voucher_date', [$from, $to]);
                        }
                    });

                // Sum debit & credit
                $debit  = (float) $vdQuery->sum('dr_amount');
                $credit = (float) $vdQuery->sum('cr_amount');

                return [
                    'parent_id'    => $account->parent_id,
                    'account_id'   => $account->id,
                    'account_type' => $account->account_type,
                    'account_name' => $account->account_name,
                    'debit'        => $debit,
                    'credit'       => $credit,
                ];
            });

        return response()->json([
            'accounts'     => $accounts,
            'total_debit'  => (float) $accounts->sum('debit'),
            'total_credit' => (float) $accounts->sum('credit'),
        ]);
    }

    private function getPaymentSummary(string $referenceType)
    {
        return DB::table('payment_details as pd')
            ->join('payments as p', 'p.id', '=', 'pd.payment_id')
            ->joinSub(
                DB::table('payment_details')
                    ->select(
                        'payment_id',
                        DB::raw('SUM(amount) as total_amount')
                    )
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->groupBy('payment_id'),
                't',
                function ($join) {
                    $join->on('t.payment_id', '=', 'pd.payment_id');
                }
            )
            ->where('pd.reference_type', $referenceType)
            ->where('pd.status', 'active')
            ->whereNull('pd.deleted_at')
            ->where('p.status', 'active')
            ->whereNull('p.deleted_at')
            ->groupBy('pd.reference_id')
            ->select(
                'pd.reference_id',
                DB::raw('SUM(pd.amount) as receive_amount'),
                DB::raw("
                SUM(
                    CASE
                        WHEN t.total_amount > 0
                        THEN (pd.amount / t.total_amount) * p.discount
                        ELSE 0
                    END
                ) as discount_amount
            ")
            )
            ->get()
            ->keyBy('reference_id');
    }

    private function mergeReceivableRow(
        array &$receivables,
        $clientId,
        $clientCode,
        $clientName,
        $clientMobile,
        $billedAmount,
        $receivedAmount,
        $discountAmount
    ) {

        $billedAmount   = (float) ($billedAmount ?? 0);
        $receivedAmount = (float) ($receivedAmount ?? 0);
        $discountAmount = (float) ($discountAmount ?? 0);

        $dueAmount = $billedAmount - ($receivedAmount + $discountAmount);

        if ($dueAmount <= 0) {
            return;
        }

        if (!isset($receivables[$clientId])) {

            $receivables[$clientId] = [
                'clientid'          => $clientCode,
                'client_name'       => $clientName,
                'client_mobile'     => $clientMobile,
                'bill_amount'       => 0,
                'received_amount'   => 0,
                'discount_amount'   => 0,
                'total_due'       => 0,
            ];
        }

        $receivables[$clientId]['bill_amount'] += $billedAmount;
        $receivables[$clientId]['received_amount'] += $receivedAmount;
        $receivables[$clientId]['discount_amount'] += $discountAmount;
        $receivables[$clientId]['total_due'] += $dueAmount;
    }

    private function processReceivableRows(
        $rows,
        array &$receivables,
        $paymentSummary
    ) {

        foreach ($rows as $row) {

            $summary = $paymentSummary[$row->id] ?? null;

            $receivedAmount = (float) ($summary->receive_amount ?? 0);
            $discountAmount = (float) ($summary->discount_amount ?? 0);

            $this->mergeReceivableRow(
                $receivables,
                $row->client_id,
                $row->clientid,
                $row->client_name,
                $row->client_mobile,
                $row->billed_amount,
                $receivedAmount,
                $discountAmount
            );
        }
    }

    public function getReceivable($searchdata)
    {
        $from = !empty($searchdata['from_date'])
            ? vue_to_server_date($searchdata['from_date'])
            : date('Y-m-01');

        $to = !empty($searchdata['to_date'])
            ? vue_to_server_date($searchdata['to_date'])
            : date('Y-m-t');

        $clientId = $searchdata['client_id'] ?? null;
        $serviceId = $searchdata['service_id'] ?? null;

        $receivables = [];

        $invoiceSummary = $this->getPaymentSummary('Invoice');
        $bandwidthSummary = $this->getPaymentSummary('BandwidthHistory');
        // dd($invoiceSummary, $bandwidthSummary);

        // Bandwidth
        $bandwidthReceivable = DB::table('bandwidth_histories as bh')
            ->leftJoin('clients', 'bh.client_id', '=', 'clients.id')
            ->select(
                'bh.id',
                'bh.client_id',
                'clients.clientid',
                'clients.mobile as client_mobile',
                'clients.name as client_name',
                DB::raw('COALESCE(bh.total_include_amount,0) as billed_amount')
            )
            ->where('bh.type', 'Sale')
            ->where('bh.is_closed', 0)
            ->whereNull('bh.deleted_at')
            ->whereBetween('bh.transaction_date', [$from, $to])
            ->when($clientId, fn($q) => $q->where('bh.client_id', $clientId))
            ->when($serviceId, fn($q) => $q->where('clients.service_id', $serviceId))
            ->get();

        $this->processReceivableRows(
            $bandwidthReceivable,
            $receivables,
            $bandwidthSummary
        );

        // Invoice
        $invoiceReceivable = DB::table('invoices')
            ->leftJoin('clients', 'invoices.client_id', '=', 'clients.id')
            ->select(
                'invoices.id',
                'invoices.client_id',
                'clients.clientid',
                'clients.mobile as client_mobile',
                'clients.name as client_name',
                DB::raw('COALESCE(invoices.amount,0) as billed_amount')
            )
            ->where('invoices.is_closed', 0)
            ->whereNull('invoices.deleted_at')
            ->whereBetween('invoices.invoice_date', [$from, $to])
            ->when($clientId, fn($q) => $q->where('invoices.client_id', $clientId))
            ->when($serviceId, fn($q) => $q->where('clients.service_id', $serviceId))
            ->get();

        $this->processReceivableRows(
            $invoiceReceivable,
            $receivables,
            $invoiceSummary
        );

        $collection = collect($receivables)->values();

        return response()->json([
            'from' => $from,
            'to' => $to,

            'total_bill_amount' => round($collection->sum('bill_amount'), 2),
            'total_received_amount' => round($collection->sum('received_amount'), 2),
            'total_discount_amount' => round($collection->sum('discount_amount'), 2),
            'grand_total' => round($collection->sum('total_due'), 2),

            'receivables' => $collection,
        ]);
    }

    private function getPaidAmount($referenceType, $referenceId)
    {
        return PaymentDetail::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->sum('amount');
    }

    public function getPayable($searchdata)
    {
        $from = $searchdata['from_date'] ?? date('Y-m-01');
        $from = vue_to_server_date($from);

        $to = $searchdata['to_date'] ?? date('Y-m-t');
        $to = vue_to_server_date($to);

        $uplink_provider_id = $searchdata['uplink_provider_id'] ?? null;
        $supplier_id        = $searchdata['supplier_id'] ?? null;
        $employee_id        = $searchdata['employee_id'] ?? null;

        $payables = collect([]);

        // Bandwidth Purchase Payable

        $bandwidthQuery = BandwidthHistory::with(['uplink_provider'])
            ->where('type', 'Purchase')
            ->where('is_closed', 0)
            ->whereNull('deleted_at')
            ->whereBetween('transaction_date', [$from, $to]);

        if ($uplink_provider_id) {
            $bandwidthQuery->where('uplink_provider_id', $uplink_provider_id);
        }

        $bandwidth = $bandwidthQuery->get()->map(function ($row) {
            $paid = $this->getPaidAmount('BandwidthHistory', $row->id);

            return [
                'reference_type' => 'BandwidthHistory',
                'reference_id'   => $row->id,
                'name'           => $row->uplink_provider->org_name ?? 'Unknown Provider',
                'source'         => 'Bandwidth Purchase',
                'date'           => $row->transaction_date,
                'payable'        => (float) $row->total_include_amount,
                'paid'           => (float) $paid,
                'outstanding'    => round($row->total_include_amount - $paid, 2),
            ];
        })->filter(fn($r) => $r['outstanding'] > 0);

        $payables = $payables->merge($bandwidth);


        //   Purchase Payable (Supplier)
        $purchaseQuery = Purchase::with('supplier')
            ->where('is_closed', 0)
            ->whereNull('deleted_at')
            ->whereBetween('purchase_date', [$from, $to]);

        if ($supplier_id) {
            $purchaseQuery->where('supplier_id', $supplier_id);
        }

        $purchase = $purchaseQuery->get()->map(function ($row) {
            $paid = $this->getPaidAmount('Purchase', $row->id);

            return [
                'reference_type' => 'Purchase',
                'reference_id'   => $row->id,
                'name'           => $row->supplier->org_name ?? 'Unknown Supplier',
                'source'         => 'Purchase',
                'date'           => $row->purchase_date,
                'payable'        => (float) $row->total_amount,
                'paid'           => (float) $paid,
                'outstanding'    => round($row->total_amount - $paid, 2),
            ];
        })->filter(fn($r) => $r['outstanding'] > 0);

        $payables = $payables->merge($purchase);

        // Expense Payable (Employee / Office)
        $expenseDetailQuery = ExpenseDetail::with([
            'expense.employee'
        ])
            ->where('is_closed', 0)
            ->whereNull('deleted_at')
            ->whereHas('expense', function ($q) use ($from, $to, $employee_id) {
                $q->whereNotNull('approved_by')
                    ->whereNull('deleted_at')
                    ->whereBetween('expense_date', [$from, $to]);

                if ($employee_id) {
                    $q->where('employee_id', $employee_id);
                }
            });

        $expenseDetails = $expenseDetailQuery->get()->map(function ($row) {

            $paid = $this->getPaidAmount('ExpenseDetail', $row->id);
            $payable = (float) $row->amount;

            return [
                'reference_type' => 'ExpenseDetail',
                'reference_id'   => $row->id,
                'name'           => $row->expense->employee_id
                    ? ($row->expense->employee->full_name ?? '')
                    : 'Office Expense',
                'source'         => $row->expense->employee_id
                    ? 'Employee Expense'
                    : 'Office Expense',
                'date'           => $row->expense->expense_date,
                'payable'        => $payable,
                'paid'           => (float) $paid,
                'outstanding'    => round($payable - $paid, 2),
            ];
        })
            ->filter(fn($r) => $r['outstanding'] > 0);

        $payables = $payables->merge($expenseDetails);

        // salary sheet
        $salaryQuery = SalarySheetDetail::with(['employee', 'salary_sheet'])
            ->whereNull('salary_sheet_details.deleted_at')
            ->where('is_paid', 0)
            ->whereHas('salary_sheet', function ($q) use ($from, $to) {
                $q->whereNotNull('approved_by')
                    ->whereNull('deleted_at')
                    ->whereBetween('generated_date', [$from, $to]);
            });

        if ($employee_id) {
            $salaryQuery->where('employee_id', $employee_id);
        }

        $salary = $salaryQuery->get()->map(function ($row) {

            $amount = (float) ($row->total ?? 0);

            return [
                'reference_type' => 'SalarySheetDetail',
                'reference_id'   => $row->id,
                'name'           => $row->employee->full_name ?? '',
                'source'         => 'Salary Sheet',
                'date'           => $row->salary_sheet->generated_date ?? null,
                'payable'        => $amount,
                'paid'           => 0,
                'outstanding'    => $amount,
            ];
        })
            ->filter(fn($r) => $r['outstanding'] > 0);

        $payables = $payables->merge($salary);
        // dd($payables);

        // Commission Payable
        $commissionQuery = Commission::with(['employee'])
            ->whereNull('deleted_at')
            ->where('is_closed', 0)
            ->whereNotNull('approved_by');

        if ($employee_id) {
            $commissionQuery->where('employee_id', $employee_id);
        }

        // Date filter (created_at diye filter korchi)
        $commissionQuery->whereBetween('created_at', [$from, $to]);

        $commissions = $commissionQuery->get()->map(function ($row) {

            $amount = (float) ($row->amount ?? 0);

            return [
                'reference_type' => 'Commission',
                'reference_id'   => $row->id,
                'name'           => $row->employee->full_name ?? 'Reference Commission',
                'source'         => 'Commission',
                'date'           => date('d M, Y', strtotime($row->created_at)),
                'payable'        => $amount,
                'paid'           => 0,
                'outstanding'    => $amount,
            ];
        })
            ->filter(fn($r) => $r['outstanding'] > 0);

        $payables = $payables->merge($commissions);

        return response()->json([
            'from'              => $from,
            'to'                => $to,
            'total_payable'     => round($payables->sum('payable'), 2),
            'total_paid'        => round($payables->sum('paid'), 2),
            'total_outstanding' => round($payables->sum('outstanding'), 2),
            'payables'          => $payables,
        ]);
    }

    public function getBalancesheet($searchdata)
    {
        // date handling
        $from = !empty($searchdata['from_date'])
            ? vue_to_server_date($searchdata['from_date'])
            : null;

        $to = !empty($searchdata['to_date'])
            ? vue_to_server_date($searchdata['to_date'])
            : date('Y-m-d');

        // ASSETS (DR - CR) up to TO DATE
        $assets = Account::where('account_type', 'Asset')
            ->whereNull('deleted_at') // soft delete check
            ->get()
            ->map(function ($a) use ($to) {
                $query = VoucherDetail::where('account_id', $a->id)
                    ->whereNull('deleted_at') // soft delete check
                    ->whereHas('voucher', function ($q) use ($to) {
                        $q->where('voucher_date', '<=', $to)
                            ->whereNull('deleted_at'); // soft delete check
                    });

                $totalDr = (float) $query->clone()->sum('dr_amount');
                $totalCr = (float) $query->clone()->sum('cr_amount');

                return [
                    'code'      => $a->account_code,
                    'name'      => $a->account_name,
                    'dr_amount' => $totalDr,
                    'cr_amount' => $totalCr,
                    'balance'   => $totalDr - $totalCr,
                ];
            });

        //LIABILITIES (CR - DR) up to TO DATE
        $liabilities = Account::where('account_type', 'Liability')
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($a) use ($to) {

                $query = VoucherDetail::where('account_id', $a->id)
                    ->whereNull('deleted_at')
                    ->whereHas(
                        'voucher',
                        fn($q) => $q->where('voucher_date', '<=', $to)
                            ->whereNull('deleted_at')
                    );

                $totalDr = (float) $query->clone()->sum('dr_amount');
                $totalCr = (float) $query->clone()->sum('cr_amount');
                $balance = $totalCr - $totalDr;

                return [
                    'code'      => $a->account_code,
                    'name'      => $a->account_name,
                    'dr_amount' => $totalDr,
                    'cr_amount' => $totalCr,
                    'balance'   => (float) $balance,
                ];
            });
        // dd($liabilities);

        // EQUITY (CR - DR) up to TO DATE
        $equity = Account::where('account_type', 'Equity')
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($a) use ($to) {

                $query = VoucherDetail::where('account_id', $a->id)
                    ->whereNull('deleted_at')
                    ->whereHas('voucher', fn($q) => $q->where('voucher_date', '<=', $to)
                        ->whereNull('deleted_at'));

                $totalDr = (float) $query->clone()->sum('dr_amount');
                $totalCr = (float) $query->clone()->sum('cr_amount');
                $balance = $totalCr - $totalDr;

                return [
                    'code'      => $a->account_code,
                    'name'      => $a->account_name,
                    'dr_amount' => $totalDr,
                    'cr_amount' => $totalCr,
                    'balance'   => (float) $balance,
                ];
            });

        return response()->json([
            'from_date'                 => $from,
            'to_date'                   => $to,
            'assets'                     => $assets,
            'liabilities'                => $liabilities,
            'equity'                     => $equity,
            'total_assets'               => round($assets->sum('balance'), 2),
            'total_liabilities_equity'   => round($liabilities->sum('balance') + $equity->sum('balance'), 2),
        ]);
    }

    public function getClientLedger($searchdata)
    {
        $from = !empty($searchdata['from_date'])
            ? vue_to_server_date($searchdata['from_date'])
            : date('Y-m-01');

        $to = !empty($searchdata['to_date'])
            ? vue_to_server_date($searchdata['to_date'])
            : date('Y-m-t');

        $client_id = $searchdata['client_id'] ?? null;

        if (!$client_id) {
            return response()->json([
                'status'  => false,
                'message' => 'Client ID is required'
            ], 422);
        }

        $client = Client::find($client_id);
        if (!$client) {
            return response()->json([
                'status'  => false,
                'message' => 'Client not found'
            ], 404);
        }

        $receivableAccountId = Account::where('system_key_name', 'accounts-receivable')->first()?->id; // Accounts Receivable

        //Opening Balance (Before from_date)
        $openingBalance = VoucherDetail::query()
            ->join('vouchers', 'voucher_details.voucher_id', '=', 'vouchers.id')
            ->where('voucher_details.reference_type', 'Client')
            ->where('voucher_details.reference_id', $client_id)
            ->where('voucher_details.account_id', $receivableAccountId)
            ->whereDate('vouchers.voucher_date', '<', $from)
            ->sum(DB::raw('voucher_details.dr_amount - voucher_details.cr_amount'));

        // Ledger rows (from_date to to_date)
        $ledger = VoucherDetail::query()
            ->select([
                'voucher_details.id',
                'voucher_details.voucher_id',
                'voucher_details.account_id',
                'voucher_details.dr_amount',
                'voucher_details.cr_amount',
                'voucher_details.created_at',
                'vouchers.voucherno',
                'vouchers.voucher_date',
                'accounts.account_name',
                'accounts.account_type',
            ])
            ->join('vouchers', 'voucher_details.voucher_id', '=', 'vouchers.id')
            ->join('accounts', 'voucher_details.account_id', '=', 'accounts.id')
            ->where('voucher_details.reference_type', 'Client')
            ->where('voucher_details.reference_id', $client_id)
            ->whereBetween('vouchers.voucher_date', [$from, $to])
            ->orderBy('vouchers.voucher_date')
            ->orderBy('voucher_details.id')
            ->get();

        //Running Balance with Opening Balance
        $balance = (float) $openingBalance;

        $ledger = $ledger->map(function ($row) use (&$balance, $receivableAccountId) {

            if ((int)$row->account_id === $receivableAccountId) {
                $balance += (float)$row->dr_amount - (float)$row->cr_amount;
            }

            $row->running_balance = $balance;

            return $row;
        });

        // Prepend Opening Balance row
        $openingRow = (object) [
            'id'             => null,
            'voucher_id'     => null,
            'account_id'     => null,
            'dr_amount'      => 0,
            'cr_amount'      => 0,
            'created_at'     => null,
            'voucherno'      => null,
            'voucher_date'   => $from,
            'account_name'   => 'Previous Due',
            'account_type'   => null,
            'running_balance' => $openingBalance,
        ];

        $ledger->prepend($openingRow);

        return response()->json([
            'client'          => $client,
            'opening_balance' => $openingBalance,
            'records'         => $ledger
        ]);
    }

    public function getUplinkProviderLedger($searchdata)
    {
        $from = !empty($searchdata['from_date'])
            ? vue_to_server_date($searchdata['from_date'])
            : date('Y-m-01');

        $to = !empty($searchdata['to_date'])
            ? vue_to_server_date($searchdata['to_date'])
            : date('Y-m-t');

        $uplink_provider_id = $searchdata['uplink_provider_id'] ?? null;

        if (!$uplink_provider_id) {
            return response()->json([
                'status'  => false,
                'message' => 'Uplink Provider ID is required'
            ], 422);
        }

        $uplinkprovider = UplinkProvider::find($uplink_provider_id);
        if (!$uplinkprovider) {
            return response()->json([
                'status'  => false,
                'message' => 'Uplink Provider not found'
            ], 404);
        }

        $payableAccountId = Account::where('system_key_name', 'accounts-payable')->first()?->id; // Accounts Receivable

        /**
         * 🔹 Opening Balance (Before from_date)
         */
        $openingBalance = VoucherDetail::query()
            ->join('vouchers', 'voucher_details.voucher_id', '=', 'vouchers.id')
            ->where('voucher_details.reference_type', 'UplinkProvider')
            ->where('voucher_details.reference_id', $uplink_provider_id)
            ->where('voucher_details.account_id', $payableAccountId)
            ->whereDate('vouchers.voucher_date', '<', $from)
            ->sum(DB::raw('voucher_details.cr_amount - voucher_details.dr_amount'));

        /**
         * 🔹 Ledger rows (from_date to to_date)
         */
        $ledger = VoucherDetail::query()
            ->select([
                'voucher_details.id',
                'voucher_details.voucher_id',
                'voucher_details.account_id',
                'voucher_details.dr_amount',
                'voucher_details.cr_amount',
                'voucher_details.created_at',
                'vouchers.voucherno',
                'vouchers.voucher_date',
                'accounts.account_name',
                'accounts.account_type',
            ])
            ->join('vouchers', 'voucher_details.voucher_id', '=', 'vouchers.id')
            ->join('accounts', 'voucher_details.account_id', '=', 'accounts.id')
            ->where('voucher_details.reference_type', 'UplinkProvider')
            ->where('voucher_details.reference_id', $uplink_provider_id)
            ->whereBetween('vouchers.voucher_date', [$from, $to])
            ->orderBy('vouchers.voucher_date')
            ->orderBy('voucher_details.id')
            ->get();

        /**
         * 🔹 Running Balance with Opening Balance
         */
        $balance = (float) $openingBalance;

        $ledger = $ledger->map(function ($row) use (&$balance, $payableAccountId) {

            if ((int)$row->account_id === $payableAccountId) {
                $balance +=  (float)$row->cr_amount - (float)$row->dr_amount;
            }

            $row->running_balance = $balance;
            return $row;
        });

        /**
         * 🔹 Prepend Opening Balance row
         */
        $openingRow = (object) [
            'id'             => null,
            'voucher_id'     => null,
            'account_id'     => null,
            'dr_amount'      => 0,
            'cr_amount'      => 0,
            'created_at'     => null,
            'voucherno'      => null,
            'voucher_date'   => $from,
            'account_name'   => 'Previous Due',
            'account_type'   => null,
            'running_balance' => $openingBalance,
        ];

        $ledger->prepend($openingRow);

        return response()->json([
            'uplinkprovider'  => $uplinkprovider,
            'opening_balance' => $openingBalance,
            'records'         => $ledger
        ]);
    }

    public function getSupplierLedger($searchdata)
    {
        $from = !empty($searchdata['from_date'])
            ? vue_to_server_date($searchdata['from_date'])
            : date('Y-m-01');

        $to = !empty($searchdata['to_date'])
            ? vue_to_server_date($searchdata['to_date'])
            : date('Y-m-t');

        $supplier_id = $searchdata['supplier_id'] ?? null;

        if (!$supplier_id) {
            return response()->json([
                'type'    => 'error',
                'message' => 'Supplier ID is required'
            ], 422);
        }

        $supplier = Supplier::find($supplier_id);
        if (!$supplier) {
            return response()->json([
                'status'  => false,
                'message' => 'Supplier not found'
            ], 404);
        }

        $payableAccountId = Account::where('system_key_name', 'accounts-payable')->first()?->id; // Accounts Receivable

        /**
         * 🔹 Opening Balance (Before from_date)
         */
        $openingBalance = VoucherDetail::query()
            ->join('vouchers', 'voucher_details.voucher_id', '=', 'vouchers.id')
            ->where('voucher_details.reference_type', 'Supplier')
            ->where('voucher_details.reference_id', $supplier_id)
            ->where('voucher_details.account_id', $payableAccountId)
            ->whereDate('vouchers.voucher_date', '<', $from)
            ->sum(DB::raw('voucher_details.cr_amount - voucher_details.dr_amount'));

        /**
         * 🔹 Ledger rows (from_date to to_date)
         */
        $ledger = VoucherDetail::query()
            ->select([
                'voucher_details.id',
                'voucher_details.voucher_id',
                'voucher_details.account_id',
                'voucher_details.dr_amount',
                'voucher_details.cr_amount',
                'voucher_details.created_at',
                'vouchers.voucherno',
                'vouchers.voucher_date',
                'accounts.account_name',
                'accounts.account_type',
            ])
            ->join('vouchers', 'voucher_details.voucher_id', '=', 'vouchers.id')
            ->join('accounts', 'voucher_details.account_id', '=', 'accounts.id')
            ->where('voucher_details.reference_type', 'Supplier')
            ->where('voucher_details.reference_id', $supplier_id)
            ->whereBetween('vouchers.voucher_date', [$from, $to])
            ->orderBy('vouchers.voucher_date')
            ->orderBy('voucher_details.id')
            ->get();

        /**
         * 🔹 Running Balance with Opening Balance
         */
        $balance = (float) $openingBalance;

        $ledger = $ledger->map(function ($row) use (&$balance, $payableAccountId) {

            if ((int)$row->account_id === $payableAccountId) {
                $balance +=  (float)$row->cr_amount - (float)$row->dr_amount;
            }

            $row->running_balance = $balance;
            return $row;
        });

        /**
         * 🔹 Prepend Opening Balance row
         */
        $openingRow = (object) [
            'id'             => null,
            'voucher_id'     => null,
            'account_id'     => null,
            'dr_amount'      => 0,
            'cr_amount'      => 0,
            'created_at'     => null,
            'voucherno'      => null,
            'voucher_date'   => $from,
            'account_name'   => 'Previous Due',
            'account_type'   => null,
            'running_balance' => $openingBalance,
        ];

        $ledger->prepend($openingRow);

        return response()->json([
            'supplier'  => $supplier,
            'opening_balance' => $openingBalance,
            'records'         => $ledger
        ]);
    }

    public function getEmployeeLedger($searchdata)
    {
        $from = !empty($searchdata['from_date'])
            ? vue_to_server_date($searchdata['from_date'])
            : date('Y-m-01');

        $to = !empty($searchdata['to_date'])
            ? vue_to_server_date($searchdata['to_date'])
            : date('Y-m-t');

        $employee_id = $searchdata['employee_id'] ?? null;

        if (!$employee_id) {
            return response()->json([
                'type'    => 'error',
                'message' => 'Employee ID is required'
            ], 422);
        }

        $employee = Employee::find($employee_id);
        if (!$employee) {
            return response()->json([
                'status'  => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $accountPayableId = Account::where('system_key_name', 'accounts-payable')->value('id');
        $payableAccountId = Account::where('system_key_name', 'salary-payable')->value('id');
        $bonuspayableAccountId = Account::where('system_key_name', 'bonus-payable')->value('id');
        $commissionPaableAccountID = Account::where('system_key_name', 'commission-payable')->value('id');
        $salaryDeductionAccountId = Account::where('system_key_name', 'employee-salary-deduction')->value('id');
        $loanAccountId    = Account::where('system_key_name', 'employee-loan-receivable')->value('id');

        if (!$payableAccountId || !$loanAccountId) {
            return response()->json([
                'status'  => false,
                'message' => 'Required accounts not configured (salary-payable / employee-loan-receivable)'
            ], 500);
        }

        /**
         * 🔹 Opening Balance (Before from_date)
         */
        $openingPayable = VoucherDetail::query()
            ->join('vouchers', 'voucher_details.voucher_id', '=', 'vouchers.id')
            ->where('voucher_details.reference_type', 'Employee')
            ->where('voucher_details.reference_id', $employee_id)
            ->whereIn('voucher_details.account_id', [$accountPayableId, $payableAccountId, $bonuspayableAccountId, $commissionPaableAccountID])
            ->whereDate('vouchers.voucher_date', '<', $from)
            ->sum(DB::raw('voucher_details.cr_amount - voucher_details.dr_amount'));

        $openingLoan = VoucherDetail::query()
            ->join('vouchers', 'voucher_details.voucher_id', '=', 'vouchers.id')
            ->where('voucher_details.reference_type', 'Employee')
            ->where('voucher_details.reference_id', $employee_id)
            ->whereIn('voucher_details.account_id', [$loanAccountId, $salaryDeductionAccountId])
            ->whereDate('vouchers.voucher_date', '<', $from)
            ->sum(DB::raw('voucher_details.dr_amount - voucher_details.cr_amount'));

        // Net Opening Balance:
        // (+) means company owes employee
        // (-) means employee owes company
        $openingBalance = (float)$openingPayable - (float)$openingLoan;

        /**
         * 🔹 Ledger rows (from_date to to_date)
         */
        $ledger = VoucherDetail::query()
            ->select([
                'voucher_details.id',
                'voucher_details.voucher_id',
                'voucher_details.account_id',
                'voucher_details.dr_amount',
                'voucher_details.cr_amount',
                'voucher_details.created_at',
                'vouchers.voucherno',
                'vouchers.voucher_date',
                'accounts.account_name',
                'accounts.account_type',
            ])
            ->join('vouchers', 'voucher_details.voucher_id', '=', 'vouchers.id')
            ->join('accounts', 'voucher_details.account_id', '=', 'accounts.id')
            ->where('voucher_details.reference_type', 'Employee')
            ->where('voucher_details.reference_id', $employee_id)
            ->whereIn('voucher_details.account_id', [$accountPayableId, $payableAccountId, $loanAccountId, $bonuspayableAccountId, $salaryDeductionAccountId, $commissionPaableAccountID])
            ->whereBetween('vouchers.voucher_date', [$from, $to])
            ->orderBy('vouchers.voucher_date')
            ->orderBy('voucher_details.id')
            ->get();

        /**
         * 🔹 Running Balance
         */
        $balance = (float) $openingBalance;

        $ledger = $ledger->map(function ($row) use (&$balance, $accountPayableId, $payableAccountId, $loanAccountId, $bonuspayableAccountId, $salaryDeductionAccountId, $commissionPaableAccountID) {

            if (
                (int)$row->account_id === (int)$accountPayableId ||
                (int)$row->account_id === (int)$payableAccountId ||
                (int)$row->account_id === (int) $bonuspayableAccountId ||
                (int)$row->account_id === (int) $commissionPaableAccountID
            ) {
                // Employee Payable (Liability): CR increases payable, DR decreases
                $balance += (float)$row->cr_amount - (float)$row->dr_amount;
            }

            if ((int)$row->account_id === (int)$loanAccountId || (int)$row->account_id === (int)$salaryDeductionAccountId) {
                // Employee Loan Receivable (Asset): DR increases loan (employee owes), CR decreases
                $balance -= (float)$row->dr_amount - (float)$row->cr_amount;
            }

            $row->running_balance = $balance;
            return $row;
        });

        /**
         * 🔹 Opening Row
         */
        $openingRow = (object) [
            'id'               => null,
            'voucher_id'       => null,
            'account_id'       => null,
            'dr_amount'        => 0,
            'cr_amount'        => 0,
            'created_at'       => null,
            'voucherno'        => null,
            'voucher_date'     => $from,
            'account_name'     => 'Opening Balance',
            'account_type'     => null,
            'running_balance'  => $openingBalance,
        ];

        $ledger->prepend($openingRow);

        return response()->json([
            'employee'        => $employee,
            'opening_balance' => $openingBalance,
            'records'         => $ledger
        ]);
    }

    public function getClientShortLedger(array $searchdata)
    {
        $clientId = $searchdata['client_id'] ?? null;
        $fromDate = $searchdata['from_date'] ?? null;
        $toDate = $searchdata['to_date'] ?? null;
        $openingBalance = 0;

        // Validate client existence
        $client = DB::table('clients')->select('id', 'name', 'mobile', 'address')->where('id', $clientId)->first();

        if (!$client) {
            return response()->json(['error' => 'Client not found'], 404);
        }

        // Default Current Month
        $fromDate = $fromDate
            ? Carbon::parse($fromDate)->format('Y-m-d')
            : Carbon::now()->startOfMonth()->format('Y-m-d');

        $toDate = $toDate
            ? Carbon::parse($toDate)->format('Y-m-d')
            : Carbon::now()->endOfMonth()->format('Y-m-d');

        $ledger = [];

        // Previous Due Before Date Range
        $previousDueInvoice = DB::table('invoices')
            ->where('client_id', $clientId)
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->where('is_previous_due', 1)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->sum('amount');

        if (number_format($previousDueInvoice) > 0) {
            $openingBalance = $previousDueInvoice;
        } else {
            // Invoice Before Date Range
            $previousInvoice = DB::table('invoices')
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->whereDate('invoice_date', '<', $fromDate)
                ->sum('amount');

            $previousBandwidth = DB::table('bandwidth_histories')
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->whereDate('transaction_date', '<', $fromDate)
                ->sum('total_include_amount');

            // Payment Before Date Range
            $previousPayment = DB::table('payments')
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->whereDate('payment_date', '<', $fromDate)
                ->sum('amount');
            // Opening Balance
            $openingBalance = ($previousInvoice + $previousBandwidth) - $previousPayment;
        }

        // Previous Due Row
        $ledger[] = [
            'date'            => $fromDate,
            'invoice_no'      => 'Previous Due',
            'invoice_amount'  => 0,
            'paid_amount'     => 0,
            'balance'         => number_format($openingBalance, 2),
            'type'            => 'previous_due',
        ];

        //Invoice Data

        $invoiceData = DB::table('invoices')
            ->select(
                'invoice_date as date',
                'invoice_no',
                'amount as invoice_amount',
                DB::raw('0 as paid_amount'),
                DB::raw('"invoice" as type')
            )
            ->where('client_id', $clientId)
            ->where('is_previous_due', 0)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereBetween('invoice_date', [$fromDate, $toDate]);

        // Bandwidth History Data

        $bandwidthData = DB::table('bandwidth_histories')
            ->select(
                'transaction_date as date',
                DB::raw('CONCAT("BH-", bhno) as invoice_no'),
                'total_include_amount as invoice_amount',
                DB::raw('0 as paid_amount'),
                DB::raw('"bandwidth" as type')
            )
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereBetween('transaction_date', [$fromDate, $toDate]);

        // Payment Data

        $paymentData = DB::table('payments')
            ->select(
                'payment_date as date',
                'payslipno as invoice_no',
                DB::raw('0 as invoice_amount'),
                'amount as paid_amount',
                DB::raw('"payment" as type')
            )
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereBetween('payment_date', [$fromDate, $toDate]);

        // Combine and order transactions

        $transactions = $invoiceData
            ->unionAll($bandwidthData)
            ->unionAll($paymentData)
            ->orderBy('date', 'asc')
            ->get();

        // Running Balance
        $runningBalance = $openingBalance;

        foreach ($transactions as $row) {

            $runningBalance += $row->invoice_amount;
            $runningBalance -= $row->paid_amount;

            $ledger[] = [
                'date'            => $row->date,
                'invoice_no'      => $row->invoice_no,
                'invoice_amount'  => number_format($row->invoice_amount, 2),
                'paid_amount'     => number_format($row->paid_amount, 2),
                'balance'         => number_format($runningBalance, 2),
                'type'            => $row->type,
            ];
        }

        return [
            'client'        => $client,
            'from_date'     => $fromDate,
            'to_date'       => $toDate,
            'ledger'        => $ledger,
        ];
    }


    public function getServiceSales(array $searchdata)
    {
        $fromDate = $searchdata['from_date'] ?? null;
        $toDate   = $searchdata['to_date'] ?? null;

        // Default Current Month
        $fromDate = $fromDate
            ? Carbon::parse($fromDate)->format('Y-m-d')
            : Carbon::now()->startOfMonth()->format('Y-m-d');

        $toDate = $toDate
            ? Carbon::parse($toDate)->format('Y-m-d')
            : Carbon::now()->endOfMonth()->format('Y-m-d');


        // Services
        $services = DB::table('services')
            ->where('status', 'active')
            ->orderBy('sorting', 'asc')
            ->get();

        $total = [
            'invoice_amount'        => 0,
            'previous_collection'   => 0,
            'current_collection'    => 0,
            'total_collection'      => 0,
            'due_amount'            => 0,
        ];

        $report = [];

        foreach ($services as $service) {

            // Service Clients
            $clientIds = DB::table('clients')
                ->where('service_id', $service->id)
                ->pluck('id');

            /*
            |--------------------------------------------------------------------------
            | Invoice Amount
            |--------------------------------------------------------------------------
            | Current Date Range Invoice + Bandwidth
            |--------------------------------------------------------------------------
            */

            $invoiceAmount = DB::table('invoices')
                ->whereIn('client_id', $clientIds)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->whereBetween('invoice_date', [$fromDate, $toDate])
                ->sum('amount');

            $bandwidthAmount = DB::table('bandwidth_histories')
                ->whereIn('client_id', $clientIds)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->whereBetween('transaction_date', [$fromDate, $toDate])
                ->sum('total_include_amount');

            $invoiceTotal = $invoiceAmount + $bandwidthAmount;

            /*
            |--------------------------------------------------------------------------
            | Previous Collection
            |--------------------------------------------------------------------------
            | Previous invoices collected in current date range
            |--------------------------------------------------------------------------
            */

            $previousInvoiceCollection = DB::table('payment_details as pd')
                ->join('payments as p', 'p.id', '=', 'pd.payment_id')
                ->join('invoices as i', 'i.id', '=', 'pd.reference_id')
                ->where('pd.reference_type', 'Invoice')
                ->whereIn('i.client_id', $clientIds)
                ->whereDate('i.invoice_date', '<', $fromDate)
                ->whereBetween('p.payment_date', [$fromDate, $toDate])
                ->sum('pd.amount');

            $previousBandwidthCollection = DB::table('payment_details as pd')
                ->join('payments as p', 'p.id', '=', 'pd.payment_id')
                ->join('bandwidth_histories as bh', 'bh.id', '=', 'pd.reference_id')
                ->where('pd.reference_type', 'BandwidthHistory')
                ->whereIn('bh.client_id', $clientIds)
                ->whereDate('bh.transaction_date', '<', $fromDate)
                ->whereBetween('p.payment_date', [$fromDate, $toDate])
                ->sum('pd.amount');

            $previousCollection = $previousInvoiceCollection + $previousBandwidthCollection;

            /*
            |--------------------------------------------------------------------------
            | Current Collection
            |--------------------------------------------------------------------------
            | Current date range invoices collected in current date range
            |--------------------------------------------------------------------------
            */

            $currentInvoiceCollection = DB::table('payment_details as pd')
                ->join('payments as p', 'p.id', '=', 'pd.payment_id')
                ->join('invoices as i', function ($join) {
                    $join->on('i.id', '=', 'pd.reference_id')
                        ->where('pd.reference_type', 'Invoice');
                })
                ->whereIn('i.client_id', $clientIds)
                ->whereBetween('i.invoice_date', [$fromDate, $toDate])
                ->whereBetween('p.payment_date', [$fromDate, $toDate])
                ->where('pd.status', 'active')
                ->whereNull('pd.deleted_at')
                ->where('p.status', 'active')
                ->whereNull('p.deleted_at')
                ->sum('pd.amount');

            /*
            |--------------------------------------------------------------------------
            | Bandwidth Current Collection
            |--------------------------------------------------------------------------
            */

            $currentBandwidthCollection = DB::table('payment_details as pd')
                ->join('payments as p', 'p.id', '=', 'pd.payment_id')
                ->join('bandwidth_histories as bh', function ($join) {
                    $join->on('bh.id', '=', 'pd.reference_id')
                        ->where('pd.reference_type', 'BandwidthHistory');
                })
                ->whereIn('bh.client_id', $clientIds)
                ->whereBetween('bh.transaction_date', [$fromDate, $toDate])
                ->whereBetween('p.payment_date', [$fromDate, $toDate])
                ->where('pd.status', 'active')
                ->whereNull('pd.deleted_at')
                ->where('p.status', 'active')
                ->whereNull('p.deleted_at')
                ->sum('pd.amount');

            $currentCollection = $currentInvoiceCollection + $currentBandwidthCollection;

            /*
            |--------------------------------------------------------------------------
            | Total Collection
            |--------------------------------------------------------------------------
            */

            $totalCollection = $previousCollection + $currentCollection;

            /*
            |--------------------------------------------------------------------------
            | Due Amount
            |--------------------------------------------------------------------------
            */

            $dueAmount = $invoiceTotal - $currentCollection;

            $report[] = [
                'service'               => $service->title,
                'invoice_amount'        => number_format($invoiceTotal, 2),
                'previous_collection'   => number_format($previousCollection, 2),
                'current_collection'    => number_format($currentCollection, 2),
                'total_collection'      => number_format($totalCollection, 2),
                'due_amount'            => number_format($dueAmount, 2),
            ];

            $total['invoice_amount'] += $invoiceTotal;
            $total['previous_collection'] += $previousCollection;
            $total['current_collection'] += $currentCollection;
            $total['total_collection'] += $totalCollection;
            $total['due_amount'] += $dueAmount;
        }

        $total = [
            'invoice_amount'        => number_format($total['invoice_amount'], 2),
            'previous_collection'   => number_format($total['previous_collection'], 2),
            'current_collection'    => number_format($total['current_collection'], 2),
            'total_collection'      => number_format($total['total_collection'], 2),
            'due_amount'            => number_format($total['due_amount'], 2),
        ];

        return [
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'sales'     => $report,
            'total'     => $total,
        ];
    }

    /**
     * Get Commission Details Report with MAC service filter
     */
    public function getCommissionReport(array $searchdata = [])
    {
        $query = Commission::with([
            'client.service:id,title',
            'service:id,title',
            'invoice:id,invoice_no,invoice_date,amount,paid_amount,is_closed',
            'package:id,title',
            'employee:id,full_name',
            'approved_admin:id,name',
            'payment_details'
        ])->latest();

        // Date filter (by approved_date or created_at)
        if (!empty($searchdata['from_date']) && !empty($searchdata['to_date'])) {
            $from = vue_to_server_date($searchdata['from_date']);
            $to = vue_to_server_date($searchdata['to_date']);
            $query->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                  ->orWhereBetween('approved_date', [$from, $to]);
            });
        } elseif (!empty($searchdata['from_date'])) {
            $from = vue_to_server_date($searchdata['from_date']);
            $query->where(function ($q) use ($from) {
                $q->where('created_at', '>=', $from . ' 00:00:00')
                  ->orWhere('approved_date', '>=', $from);
            });
        } elseif (!empty($searchdata['to_date'])) {
            $to = vue_to_server_date($searchdata['to_date']);
            $query->where(function ($q) use ($to) {
                $q->where('created_at', '<=', $to . ' 23:59:59')
                  ->orWhere('approved_date', '<=', $to);
            });
        }

        // Client filter
        if (!empty($searchdata['client_id'])) {
            $query->where('client_id', $searchdata['client_id']);
        }

        // MAC service filter or general service filter
        if (!empty($searchdata['is_mac']) && ($searchdata['is_mac'] === '1' || $searchdata['is_mac'] === true || $searchdata['is_mac'] === 'true')) {
            $query->where(function ($q) {
                $q->where('service_id', 6)
                  ->orWhereHas('client', function ($cq) {
                      $cq->where('service_id', 6);
                  });
            });
        } elseif (!empty($searchdata['service_id'])) {
            $serviceId = $searchdata['service_id'];
            $query->where(function ($q) use ($serviceId) {
                $q->where('service_id', $serviceId)
                  ->orWhereHas('client', function ($cq) use ($serviceId) {
                      $cq->where('service_id', $serviceId);
                  });
            });
        }

        // Payment status filter
        if (!empty($searchdata['payment_status']) && $searchdata['payment_status'] !== 'all') {
            if ($searchdata['payment_status'] === 'paid') {
                $query->where(function ($q) {
                    $q->where('is_closed', 1)
                      ->orWhereRaw('paid_amount >= amount AND amount > 0');
                });
            } elseif ($searchdata['payment_status'] === 'unpaid') {
                $query->where('is_closed', 0)
                      ->where(function ($q) {
                          $q->whereNull('paid_amount')->orWhere('paid_amount', '<=', 0);
                      });
            } elseif ($searchdata['payment_status'] === 'partial') {
                $query->where('is_closed', 0)
                      ->whereRaw('paid_amount > 0 AND paid_amount < amount');
            }
        }

        // Approval status filter
        if (!empty($searchdata['approval_status']) && $searchdata['approval_status'] !== 'all') {
            if ($searchdata['approval_status'] === 'approved') {
                $query->whereNotNull('approved_by');
            } elseif ($searchdata['approval_status'] === 'pending') {
                $query->whereNull('approved_by');
            }
        }

        $records = $query->get();

        $commissions = $records->map(function ($row) {
            $serviceTitle = $row->service?->title ?? $row->client?->service?->title ?? '-';
            $isMac = ($row->service_id == 6 || ($row->client && $row->client->service_id == 6) || strtolower($serviceTitle) === 'mac');

            $commissionAmount = floatval($row->amount ?? 0);
            $paymentDetailsSum = $row->payment_details ? $row->payment_details->sum('amount') : 0;
            $paidAmount = max(floatval($row->paid_amount ?? 0), floatval($paymentDetailsSum));
            $dueAmount = max(0, $commissionAmount - $paidAmount);

            $invoiceNo = $row->invoice ? $row->invoice->invoice_no : '-';
            $invoiceAmount = $row->invoice ? floatval($row->invoice->amount ?? 0) : 0;
            $invoiceDate = $row->invoice ? $row->invoice->invoice_date : null;

            if ($row->is_closed == 1 || ($paidAmount > 0 && $dueAmount <= 0.01)) {
                $paymentStatus = 'Paid';
            } elseif ($paidAmount > 0 && $dueAmount > 0.01) {
                $paymentStatus = 'Partial';
            } else {
                $paymentStatus = 'Unpaid';
            }

            return [
                'id'                => $row->id,
                'date'              => $row->approved_date ?: ($row->created_at ? $row->created_at->format('Y-m-d') : null),
                'client_id'         => $row->client_id,
                'client_name'       => $row->client?->name ?? $row->reference_name ?? '-',
                'client_code'       => $row->client?->clientid ?? '-',
                'client_mobile'     => $row->client?->mobile ?? $row->reference_mobile ?? '-',
                'service_id'        => $row->service_id ?? $row->client?->service_id,
                'service_name'      => $serviceTitle,
                'is_mac'            => $isMac,
                'package_name'      => $row->package?->title ?? '-',
                'invoice_id'        => $row->invoice_id,
                'invoice_no'        => $invoiceNo,
                'invoice_date'      => $invoiceDate,
                'invoice_amount'    => $invoiceAmount,
                'percentage'        => floatval($row->percentage ?? 0),
                'commission_amount' => $commissionAmount,
                'paid_amount'       => $paidAmount,
                'due_amount'        => $dueAmount,
                'payment_status'    => $paymentStatus,
                'is_closed'         => $row->is_closed,
                'approved_by'       => $row->approved_admin?->name ?? null,
                'approved_date'     => $row->approved_date,
                'is_approved'       => !empty($row->approved_by),
                'remarks'           => $row->remarks,
            ];
        });

        $totalInvoices = $commissions->sum('invoice_amount');
        $totalCommission = $commissions->sum('commission_amount');
        $totalPaid = $commissions->sum('paid_amount');
        $totalDue = $commissions->sum('due_amount');

        $macCommissions = $commissions->where('is_mac', true);
        $totalMacCommission = $macCommissions->sum('commission_amount');
        $totalMacCount = $macCommissions->count();

        return [
            'from_date'             => $searchdata['from_date'] ?? null,
            'to_date'               => $searchdata['to_date'] ?? null,
            'commissions'           => $commissions,
            'total_invoices'        => round($totalInvoices, 2),
            'total_commission'      => round($totalCommission, 2),
            'total_paid'            => round($totalPaid, 2),
            'total_due'             => round($totalDue, 2),
            'total_mac_commission'  => round($totalMacCommission, 2),
            'total_mac_count'       => $totalMacCount,
            'total_count'           => $commissions->count(),
        ];
    }
}
