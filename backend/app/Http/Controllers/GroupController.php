<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GroupRequest;
use App\Http\Requests\MemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Group;
use App\Services\GroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GroupController extends Controller
{
    public function __construct(private GroupService $groups) {}

    public function index(Request $request)
    {
        return response()->json(['data' => Group::where('created_by', $request->user()->id)->whereHas('memberships', fn ($q) => $q->where('user_id', $request->user()->id)->where('role', 'owner')->whereNull('left_at'))->orderByDesc('created_at')->orderBy('id')->get()]);
    }

    public function store(GroupRequest $request)
    {
        return response()->json(['data' => $this->groups->create($request->user(), $request->validated())], 201);
    }

    public function show(Group $group)
    {
        Gate::authorize('view', $group);

        return response()->json(['data' => $group]);
    }

    public function update(GroupRequest $request, Group $group)
    {
        return response()->json(['data' => $this->groups->rename($request->user(), $group, $request->validated('name'))]);
    }

    public function destroy(Request $request, Group $group)
    {
        $this->groups->delete($request->user(), $group);

        return response()->noContent();
    }

    public function members(Group $group)
    {
        Gate::authorize('view', $group);

        return MemberResource::collection($group->memberships()->with('user')->orderBy('user_id')->get());
    }

    public function addMember(MemberRequest $request, Group $group)
    {
        return (new MemberResource($this->groups->add($request->user(), $group, $request->validated())))->response()->setStatusCode(201);
    }

    public function removeMember(Request $request, Group $group, string $user)
    {
        $this->groups->remove($request->user(), $group, $user);

        return response()->noContent();
    }
}
