<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Group;

final class SettlementService
{
    /** Pure calculation from persisted expense/split-shaped arrays, independent of HTTP/database. */
    public function calculate(iterable $expenses, array $members): array
    {
        $people = [];
        $totals = [];
        foreach ($members as $member) {
            $people[$member['id']] = ['id' => $member['id'], 'name' => $member['name']];
            $totals[$member['id']] = ['paid_total' => 0, 'share_total' => 0, 'receivable_total' => 0, 'payable_total' => 0];
        }
        $pairs = [];
        $total = 0;
        foreach ($expenses as $expense) {
            if (! empty($expense['deleted_at'])) {
                continue;
            }
            $payer = $expense['paid_by'];
            $amount = Money::cents($expense['amount']);
            $total = Money::add($total, $amount);
            $totals[$payer]['paid_total'] = Money::add($totals[$payer]['paid_total'], $amount);
            foreach ($expense['splits'] as $split) {
                $id = $split['user_id'];
                $share = Money::cents($split['amount']);
                $totals[$id]['share_total'] = Money::add($totals[$id]['share_total'], $share);
                if ($id === $payer) {
                    continue;
                }
                [$a, $b] = strcmp($id, $payer) < 0 ? [$id, $payer] : [$payer, $id];
                $key = $a.':'.$b;
                $pairs[$key] ??= ['a' => $a, 'b' => $b, 'ab' => 0, 'ba' => 0];
                $direction = $id === $a ? 'ab' : 'ba';
                $pairs[$key][$direction] = Money::add($pairs[$key][$direction], $share);
            }
        }
        $settlements = [];
        foreach ($pairs as $pair) {
            $net = $pair['ab'] - $pair['ba'];
            if ($net === 0) {
                continue;
            }
            [$from, $to] = $net > 0 ? [$pair['a'], $pair['b']] : [$pair['b'], $pair['a']];
            $amount = abs($net);
            $settlements[] = ['from' => $people[$from], 'to' => $people[$to], 'amount' => Money::decimal($amount)];
            $totals[$from]['payable_total'] = Money::add($totals[$from]['payable_total'], $amount);
            $totals[$to]['receivable_total'] = Money::add($totals[$to]['receivable_total'], $amount);
        }
        usort($settlements, fn ($a, $b) => strcmp($a['from']['id'], $b['from']['id']) ?: strcmp($a['to']['id'], $b['to']['id']));
        $summary = [];
        foreach ($totals as $id => $values) {
            $values['net_balance'] = $values['receivable_total'] - $values['payable_total'];
            $summary[] = $people[$id] + array_map(Money::decimal(...), $values);
        }

        return ['settlements' => $settlements, 'total_expenses' => Money::decimal($total), 'members' => $summary];
    }

    public function forGroup(Group $group): array
    {
        $members = $group->memberships()->with('user')->get()->map(fn ($m) => ['id' => $m->user_id, 'name' => $m->user->name])->all();

        return $this->calculate($group->expenses()->with('splits')->get()->toArray(), $members);
    }
}
