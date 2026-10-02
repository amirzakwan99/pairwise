<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Group;
use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    public function index(Request $request, Group $group)
    {
        Gate::authorize('view', $group);
        $options = $request->validate(['sort' => ['sometimes', Rule::in(['date', 'amount', 'description'])], 'direction' => ['sometimes', Rule::in(['asc', 'desc'])]]);
        $sort = ['date' => 'expense_date', 'amount' => 'amount', 'description' => 'description'][$options['sort'] ?? 'date'];

        return ExpenseResource::collection($group->expenses()->with('splits')->orderBy($sort, $options['direction'] ?? 'desc')->orderBy('id')->get());
    }

    public function store(ExpenseRequest $request, Group $group)
    {
        return (new ExpenseResource($this->expenses->save($request->user(), $group, $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Group $group, string $expense)
    {
        Gate::authorize('view', $group);

        return new ExpenseResource($group->expenses()->with('splits')->findOrFail($expense));
    }

    public function update(ExpenseRequest $request, Group $group, string $expense)
    {
        return new ExpenseResource($this->expenses->save($request->user(), $group, $request->validated(), $expense));
    }

    public function destroy(Request $request, Group $group, string $expense)
    {
        $this->expenses->delete($request->user(), $group, $expense);

        return response()->noContent();
    }
}
