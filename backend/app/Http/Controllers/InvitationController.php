<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\MemberResource;
use App\Models\Group;
use App\Services\GroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function __construct(private GroupService $groups) {}

    public function store(Request $request, Group $group)
    {
        return $this->groups->locked($group, function (Group $group) use ($request) {
            Gate::forUser($request->user())->authorize('update', $group);
            $token = bin2hex(random_bytes(32));
            $group->invite_token_hash = hash('sha256', $token);
            $group->invite_expires_at = now()->addDays(7);
            $group->save();

            return response()->json(['data' => ['token' => $token, 'expires_at' => $group->invite_expires_at->toISOString()]], 201);
        });
    }

    public function destroy(Request $request, Group $group)
    {
        $this->groups->locked($group, function (Group $group) use ($request) {
            Gate::forUser($request->user())->authorize('update', $group);
            $group->invite_token_hash = null;
            $group->invite_expires_at = null;
            $group->save();
        });

        return response()->noContent();
    }

    private function invitedGroup(string $token): Group
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);

        return Group::where('invite_token_hash', hash('sha256', $token))
            ->where('invite_expires_at', '>', now())->firstOrFail();
    }

    public function show(string $token)
    {
        $group = $this->invitedGroup($token);

        return response()->json(['data' => [
            'group' => ['id' => $group->id, 'name' => $group->name],
            'members' => $group->memberships()->with('user')
                ->where('role', 'member')->whereNull('left_at')->whereNull('account_user_id')
                ->whereHas('user', fn ($q) => $q->whereNull('password'))->get()
                ->map(fn ($member) => ['id' => $member->user_id, 'name' => $member->user->name]),
        ]]);
    }

    public function join(Request $request, string $token)
    {
        $data = $request->validate(['member_id' => ['required', 'string', 'ulid']]);
        $group = $this->invitedGroup($token);

        return $this->groups->locked($group, function (Group $group) use ($request, $token, $data) {
            // Check again after obtaining the lock: revocation/rotation may have raced this request.
            abort_unless($group->invite_token_hash === hash('sha256', $token)
                && $group->invite_expires_at?->isFuture(), 404);
            $account = $request->user();
            if ($group->created_by === $account->id || $group->memberships()
                ->where(fn ($q) => $q->where('account_user_id', $account->id)->orWhere('user_id', $account->id))->exists()) {
                throw ValidationException::withMessages(['member_id' => 'Your account already belongs to this group.']);
            }
            $member = $group->memberships()->with('user')->where('user_id', $data['member_id'])
                ->where('role', 'member')->whereNull('left_at')->firstOrFail();
            if ($member->account_user_id !== null || $member->user->password !== null) {
                throw ValidationException::withMessages(['member_id' => 'This name has already been linked to an account.']);
            }
            // Keep the participant ID used by historic payers and splits unchanged.
            $member->update(['account_user_id' => $account->id, 'contact_email' => $account->email]);

            return response()->json(['data' => ['group_id' => $group->id, 'member' => new MemberResource($member)]], 201);
        });
    }
}
