<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Money;
use App\Services\SettlementService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SettlementTest extends TestCase
{
    private array $members = [['id' => 'a', 'name' => 'Amir'], ['id' => 'b', 'name' => 'Ali'], ['id' => 'c', 'name' => 'Abu']];

    private function expense(string $payer, string $amount, array $shares): array
    {
        return ['paid_by' => $payer, 'amount' => $amount, 'splits' => array_map(fn ($id, $amount) => ['user_id' => $id, 'amount' => $amount], array_keys($shares), array_values($shares))];
    }

    private function debts(array $expenses): array
    {
        return array_map(fn ($s) => [$s['from']['id'], $s['to']['id'], $s['amount']], (new SettlementService)->calculate($expenses, $this->members)['settlements']);
    }

    public function test_single_expense(): void
    {
        self::assertSame([['b', 'a', '40.00'], ['c', 'a', '40.00']], $this->debts([$this->expense('a', '120.00', ['a' => '40.00', 'b' => '40.00', 'c' => '40.00'])]));
    }

    public function test_three_person_settlement(): void
    {
        $expenses = [
            $this->expense('a', '120.00', ['a' => '40.00', 'b' => '40.00', 'c' => '40.00']),
            $this->expense('b', '60.00', ['a' => '20.00', 'b' => '20.00', 'c' => '20.00']),
            $this->expense('c', '30.00', ['a' => '10.00', 'b' => '10.00', 'c' => '10.00']),
        ];
        self::assertSame([['b', 'a', '20.00'], ['c', 'a', '30.00'], ['c', 'b', '10.00']], $this->debts($expenses));
        $summary = (new SettlementService)->calculate($expenses, $this->members);
        self::assertSame('210.00', $summary['total_expenses']);
        self::assertSame(['50.00', '-10.00', '-40.00'], array_column($summary['members'], 'net_balance'));
        foreach ($summary['members'] as $member) {
            self::assertSame(Money::cents($member['paid_total']) - Money::cents($member['share_total']), Money::cents($member['receivable_total']) - Money::cents($member['payable_total']));
        }
    }

    public function test_multiple_expenses_and_multiple_debts_between_same_pair(): void
    {
        self::assertSame([['b', 'a', '30.00']], $this->debts([$this->expense('a', '10.00', ['b' => '10.00']), $this->expense('a', '20.00', ['b' => '20.00'])]));
    }

    public function test_pairwise_netting_and_reverse_debt(): void
    {
        self::assertSame([['a', 'b', '40.00']], $this->debts([$this->expense('b', '100.00', ['a' => '100.00']), $this->expense('a', '60.00', ['b' => '60.00'])]));
    }

    public function test_zero_debt(): void
    {
        self::assertSame([], $this->debts([$this->expense('b', '50.00', ['a' => '50.00']), $this->expense('a', '50.00', ['b' => '50.00'])]));
    }

    public function test_custom_split(): void
    {
        self::assertSame([['b', 'a', '30.00'], ['c', 'a', '20.00']], $this->debts([$this->expense('a', '100.00', ['a' => '50.00', 'b' => '30.00', 'c' => '20.00'])]));
    }

    public function test_equal_split_rounding(): void
    {
        self::assertSame(['a' => 3334, 'b' => 3333, 'c' => 3333], Money::equal(10000, ['c', 'a', 'b']));
        self::assertSame(['a' => 334, 'b' => 334, 'c' => 333], Money::equal(Money::cents('10.01'), ['a', 'b', 'c']));
        self::assertSame(10099, array_sum(Money::equal(Money::cents('100.99'), ['c', 'b', 'a'])));
        self::assertSame(['a' => 1, 'b' => 0, 'c' => 0], Money::equal(1, ['c', 'b', 'a']));
    }

    public function test_non_participating_payer(): void
    {
        self::assertSame([['a', 'b', '50.00'], ['c', 'b', '50.00']], $this->debts([$this->expense('b', '100.00', ['a' => '50.00', 'c' => '50.00'])]));
    }

    public function test_cycle_is_never_globally_cancelled_and_directional_totals_remain(): void
    {
        $expenses = [$this->expense('b', '30.00', ['a' => '30.00']), $this->expense('c', '30.00', ['b' => '30.00']), $this->expense('a', '30.00', ['c' => '30.00'])];
        self::assertSame([['a', 'b', '30.00'], ['b', 'c', '30.00'], ['c', 'a', '30.00']], $this->debts($expenses));
        foreach ((new SettlementService)->calculate($expenses, $this->members)['members'] as $member) {
            self::assertSame('0.00', $member['net_balance']);
            self::assertSame('30.00', $member['receivable_total']);
            self::assertSame('30.00', $member['payable_total']);
        }
    }

    public function test_deleted_expenses_and_zero_custom_shares(): void
    {
        $deleted = $this->expense('a', '10.00', ['b' => '10.00']);
        $deleted['deleted_at'] = '2026-10-02';
        self::assertSame([], $this->debts([$deleted, $this->expense('a', '10.00', ['a' => '10.00', 'c' => '0.00'])]));
    }

    public function test_aggregate_can_exceed_database_precision(): void
    {
        $expense = $this->expense('a', '9999999999.99', ['b' => '9999999999.99']);
        self::assertSame([['b', 'a', '19999999999.98']], $this->debts([$expense, $expense]));
        self::assertSame('0.30', Money::decimal(Money::cents('0.10') + Money::cents('0.20')));
    }

    #[DataProvider('invalidMoney')]
    public function test_invalid_money_is_rejected(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::cents($amount);
    }

    public static function invalidMoney(): array
    {
        return array_map(fn ($v) => [$v], ['-1.00', '0.001', '1e2', '10000000000', '1.', '.50', 'NaN', ' 1.00']);
    }
}
