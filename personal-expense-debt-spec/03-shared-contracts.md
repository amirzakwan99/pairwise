# Shared contracts and resolved decisions

Read this before implementing any component. This file owns cross-component interfaces and resolves ambiguities in the original specification. Category files own their detailed requirements. Change an interface here and in every affected file and test together; never invent a competing contract in a component.

## Money, identity and calculation

- Use ULID strings consistently for all primary and foreign IDs, including users. Configure models, migrations, factories, API validation and TypeScript types accordingly. Treat IDs as opaque strings in the frontend.
- MVP accepts MYR only, stored on each group, with two fractional digits. Leave room for future currencies without implementing conversion. Currency cannot change after expenses exist.
- Store expense and share amounts as DECIMAL(12,2). Send and receive money as decimal strings such as "120.00". Parse input exactly to integer cents in PHP; perform all split, debt and total calculations in integer cents on a 64-bit PHP runtime. Never cast money to floating point. Aggregate totals may exceed one expense's database precision, so do not impose that precision on summaries.
- Reject negative expense/share amounts and inputs with more than two fractional digits instead of silently rounding them. Expense amount must be positive and fit DECIMAL(12,2); custom shares may be zero. Accept up to ten whole digits and normalize accepted money strings to exactly two decimals in responses.
- Equal splits: sort selected participant IDs lexicographically, give each floor(total cents / participant count), and give one additional cent to the first remainder participants. Persist those shares. For 100.00 and three participants in sorted ID order, the shares are 33.34, 33.33, 33.33.
- Participants are unique active members, at least one is required, and the payer must be an active member. The payer may be excluded from participants. Custom shares must cover exactly the selected participants and sum exactly to the total.
- SettlementService reads persisted splits from non-deleted expenses. It adds each non-payer share as participant → payer, accumulates raw debts, then nets only within each unordered pair. Exclude zero net debts; sort results by from.id and then to.id for determinism. Never reroute debts through a third person.
- Settlements are derived read-only results, not payment records. No stored settlement table or "mark paid" feature in MVP. Expense edits/deletes recompute from source data.
- Summary: total_expenses is the sum of active expense amounts. For each member, paid_total sums expenses they paid; share_total sums their splits; receivable_total and payable_total sum incoming/outgoing pairwise debts; net_balance = receivable_total - payable_total = paid_total - share_total. A positive net_balance means owed money. Show both directional totals even when net_balance is zero.

## Database additions needed for the original features

The base tables and fields in 05-database.md are retained with these additions:

| Table | Addition | Purpose |
| --- | --- | --- |
| group_members | role: owner/member; nullable left_at | Explicit permissions and membership history |
| expenses | created_by ULID foreign key; nullable deleted_at | Identify the author independently of the payer; soft deletion |
| expense_splits | unique(expense_id, user_id) | Prevent duplicate participants |

Exactly one active owner per group. groups.created_by is the original creator and immutable; group_members.role is the authorization source. Creating a group inserts its owner membership atomically. Preserve user and membership identities referenced by historical expenses. Index membership lookups, expense group/date queries and split expense/user lookups; use foreign keys and transactional writes.

Migrations must create users, groups, group_members, expenses, then expense_splits; rollback in reverse. Roles and split types must match API validation and TypeScript unions. MVP split types are exactly equal/custom.

## Authentication and permission decisions

- Use Sanctum stateful SPA cookie/session authentication. Fetch /sanctum/csrf-cookie before authenticated writes, include credentials and the XSRF header, configure stateful domains, CORS and cookies, and require CSRF protection. Do not mix this with personal access tokens or localStorage tokens.
- Prefer one public origin in production: Nginx routes /api and /sanctum to Laravel and serves the React build. During development use a Vite proxy or an explicitly configured same-site Sanctum setup. Confirm register/login/me/logout before UI integration.
- Owner: rename/delete group, add/remove members, view/add/edit/delete any expense and view settlements. Member: view, add expenses, edit/delete expenses where expenses.created_by equals their own ID, and view settlements. Being the payer does not confer edit permission.
- Add members by exact email lookup of existing registered accounts; no email sending or invitation workflow. Adding an existing active member is rejected with validation feedback.
- Leave/remove sets left_at; it does not delete historical membership, splits or debts. Former members immediately lose access. Existing historical debt involving them remains visible to active members. Only active members can appear on new or replacement expense splits. Rejoining reactivates the same membership row as member.
- Owners cannot leave or be removed in MVP; ownership transfer is deferred. The owner may delete the group. Member lists include former members with active=false so historical names remain available; selection controls show active members only.
- Scope every nested expense/member query to its group and apply policies. Do not authorize solely from a client-provided group/user ID. Requests from former members are unauthorized too.
- Group deletion removes the whole group and its expenses/splits/members atomically. Expense deletion uses soft deletes and excludes the deleted expense and its splits from all calculations.

## API interface owned here

All IDs are strings, amounts are decimal strings, names/descriptions are strings, notes may be null. Dates use YYYY-MM-DD; timestamps use ISO 8601. Use JSON and the routes in 08-api.md plus these missing feature routes:

| Method | Path | Behavior |
| --- | --- | --- |
| GET | /sanctum/csrf-cookie | Initialize SPA CSRF cookie |
| PATCH | /api/auth/password | Change current user's password |
| DELETE | /api/groups/{group}/members/me | Leave group; register before the dynamic member route |
| GET | /api/groups/{group}/summary | Return group/member totals |

Register payload: name, email, password, password_confirmation. Login: email, password. Password change: current_password, password, password_confirmation. Group create: name and optional currency (MYR); rename: name. Add member: email.

Expense create and update payload:

```json
{
  "description": "Dinner",
  "amount": "120.00",
  "paid_by": "<user ULID>",
  "expense_date": "2026-10-02",
  "notes": null,
  "split_type": "equal",
  "participant_ids": ["<Amir ULID>", "<Ali ULID>", "<Abu ULID>"]
}
```

For custom, additionally send splits as an array of {user_id, amount}; its unique user IDs must exactly match participant_ids. For equal, omit splits; the server computes them. PATCH expense submits the complete expense form as an atomic replacement, including participants and custom splits when applicable. Client values never override created_by or group_id. Expense responses include id, group_id, created_by, description, amount, paid_by, split_type, expense_date, notes, timestamps and persisted splits [{user_id, amount}]; clients resolve member names through the group member list.

Single-resource responses use {"data": resource}; collection responses use {"data": [resources]}. Register/login/me return {"data": {"id": "...", "name": "...", "email": "..."}}. Groups expose id, name, created_by, currency and timestamps. Member resources expose id (user ID), name, email, role and active. Summary uses {"data": {"currency": "MYR", "total_expenses": "210.00", "members": [{"id": "...", "name": "...", "paid_total": "...", "share_total": "...", "receivable_total": "...", "payable_total": "...", "net_balance": "..."}]}}.

The settlement endpoint intentionally keeps the existing top-level {"currency": "MYR", "settlements": [{"from": {"id": "...", "name": "..."}, "to": {"id": "...", "name": "..."}, "amount": "..."}]} contract from 08-api.md. Each entry describes a required payment from debtor to creditor.

Use 200 for reads/updates/login/password changes, 201 for creates/register, 204 with no JSON body for logout/deletes/leave; 401 unauthenticated, 403 unauthorized, 404 missing or wrong-group resource, 422 validation and 429 throttled. Validation errors use the existing {message, errors} shape; other errors have message. Handle session/CSRF expiry with actionable frontend feedback.

GET expenses accepts sort=date|amount|description and direction=asc|desc; default date descending, with ID as a stable tie-breaker. No pagination is required for the small-group MVP. GET members includes active and former members; GET groups lists only current memberships.

## Corrections to source examples

- Source section 32 had a reversed Amir/Ali result. Dinner 120 paid by Amir, Grab 60 paid by Ali and Drinks 30 paid by Abu produce **Ali → Amir 20.00**, **Abu → Amir 30.00**, **Abu → Ali 10.00**. The split files correct both occurrences.
- Source section 13 said both Ali and Abu pay each other 25.00. With its stated example debts, Abu **receives** 25.00 from Ali. The UI example is corrected.
- UI examples in separate sections describe independent illustrative scenarios. Do not combine their figures into one fixture. Test the exact three-expense fixture above separately from seed data that also includes Hotel.
- Invite links, percentage splits, settlement history, "mark paid" and PWA are post-MVP. Email lookup, equal/custom splits and read-only pairwise settlements form the MVP.
