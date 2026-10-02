<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SettlementController extends Controller
{
    public function __construct(private SettlementService $settlements) {}

    public function show(Group $group)
    {
        Gate::authorize('view', $group);
        $result = DB::transaction(fn () => $this->settlements->forGroup($group));

        return response()->json(['currency' => $group->currency, 'settlements' => $result['settlements']]);
    }

    public function summary(Group $group)
    {
        Gate::authorize('view', $group);
        $result = DB::transaction(fn () => $this->settlements->forGroup($group));

        return response()->json(['data' => ['currency' => $group->currency, 'total_expenses' => $result['total_expenses'], 'members' => $result['members']]]);
    }
}
