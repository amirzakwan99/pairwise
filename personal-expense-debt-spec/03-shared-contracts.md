# Shared contracts and resolved decisions

Read this before implementing any component. This file owns cross-component interfaces and resolves ambiguities in the original specification. Category files own their detailed requirements. Change an interface here and in every affected file and test together; never invent a competing contract in a component.

## Creator-managed groups (updated 2026-10-02)

The creator can add name-only participants and invite them to register or log in and claim their existing name. Joined active members can view the group and add/edit any expense. The creator alone manages members, invitations, group settings and deletion, including expense deletion. Email alone grants no access.

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
| users | nullable email and password for guest identities | Named participants without login accounts; registered creators still require credentials |
| group_members | role: owner/member; nullable left_at, contact_email and account_user_id | Creator ownership, optional contacts, linked login accounts and membership history |
| groups | nullable invite_token_hash and invite_expires_at | One rotating/revocable invitation, expiring after seven days |
| expenses | created_by ULID foreign key; nullable deleted_at | Identify the author independently of the payer; soft deletion |
| expense_splits | unique(expense_id, user_id) | Prevent duplicate participants |

Exactly one active owner per group. groups.created_by is the original creator and immutable; owner access requires that creator ID and an active owner membership. Other accounts require an active membership explicitly linked through an invitation. Legacy registered membership and contact email alone grant no access. Creating a group inserts its owner membership atomically. Preserve user and membership identities referenced by historical expenses. Index membership lookups, expense group/date queries and split expense/user lookups; use foreign keys and transactional writes.

Migrations must create users, groups, group_members, expenses, then expense_splits; rollback in reverse. Roles and split types must match API validation and TypeScript unions. MVP split types are exactly equal/custom.

Existing installations use an additive migration to make identity email/password nullable and add contact_email without changing historical IDs or debts. Rolling back nullable credentials is refused while guests exist; restore a pre-migration backup instead of inventing credentials or deleting history.

## Authentication and permission decisions

- Use Sanctum stateful SPA cookie/session authentication. Fetch /sanctum/csrf-cookie before authenticated writes, include credentials and the XSRF header, configure stateful domains, CORS and cookies, and require CSRF protection. Do not mix this with personal access tokens or localStorage tokens.
- Prefer one public origin in production: Nginx routes /api and /sanctum to Laravel and serves the React build. During development use a Vite proxy or an explicitly configured same-site Sanctum setup. Confirm register/login/me/logout before UI integration.
- The creator and invited, linked active accounts may view the group, members, expenses, summaries and settlements and create/edit every expense. Only the creator may rename/delete the group, manage participants/invitations and delete expenses. Payer, participant, optional email, original expense authorship and legacy registered membership alone never grant access.
- Add members with required name and optional email. New participants receive group-local guest identities (ULID user rows with null email/password); optional email is stored on group_members.contact_email. Never look up or link a registered account by that email or generate guest passwords. Reject duplicate active names case-insensitively within a group. The same name in another group receives a separate identity.
- The creator generates a random 256-bit invitation link, valid for seven days and shared manually. Store only its SHA-256 hash; rotation invalidates the previous link and revocation stops new joins. Existing joined members remain linked. Invitation preview/join require authentication and a valid token. Preview exposes only group ID/name and eligible participant IDs/names, excluding owner, former, claimed and legacy registered identities.
- A joining account selects an unclaimed active guest name. Atomically set account_user_id to the authenticated account ID and contact_email to its login email. Preserve membership user_id, guest name, guest null credentials, payers and splits. One account may claim one identity per group; one identity may be claimed once. Serialize claims and invitation rotation/revocation with the group lock; recheck expiry/token after locking. No inferred access from matching names/emails. The holder of an invitation can choose any eligible name; the creator shares links only with trusted group participants.
- Creator removal sets left_at without deleting historical identities, shares or debts. The creator still sees historical debts involving former participants. Only active members can be selected on new or replacement expenses. Adding a previously removed name reactivates the same identity and membership as member; an omitted email retains its previous contact email. There is no participant self-service leave flow.
- The creator cannot be removed in MVP; ownership transfer is deferred. The owner may delete the group. Member lists include former members with active=false so historical names remain available; selection controls show active members only.
- Scope every nested expense/member query to its group and apply policies. Do not authorize solely from a client-provided group/user ID. Unlinked accounts, registered legacy members and inactive participants cannot access the group. Removing a linked member immediately revokes group access; re-adding their name retains the account link and restores access without changing history.
- Group deletion removes the whole group and its expenses/splits/members atomically. Expense deletion uses soft deletes and excludes the deleted expense and its splits from all calculations.

## API interface owned here

All IDs are strings, amounts are decimal strings, names/descriptions are strings, notes may be null. Dates use YYYY-MM-DD; timestamps use ISO 8601. Use JSON and the routes in 08-api.md plus these missing feature routes:

| Method | Path | Behavior |
| --- | --- | --- |
| GET | /sanctum/csrf-cookie | Initialize SPA CSRF cookie |
| PATCH | /api/auth/password | Change current user's password |
| GET | /api/groups/{group}/summary | Return group/member totals |
| POST | /api/groups/{group}/invitation | Creator generates/replaces link; returns data.token and data.expires_at |
| DELETE | /api/groups/{group}/invitation | Creator revokes link; returns 204 |
| GET | /api/invitations/{token} | Authenticated preview: data.group (id/name), data.members (id/name) |
| POST | /api/invitations/{token}/join | Body member_id (participant ULID); returns 201 data.group_id and data.member |

Register payload: name, email, password, password_confirmation. Login: email, password. Password change: current_password, password, password_confirmation. Group create: name, optional currency (MYR), and optional member_names (a list of nonblank names, each at most 255 characters); rename: name only. Add member: name (required) and email (optional, nullable).

Group creation adds the creator automatically and creates all initial named participants in the same transaction. Reject duplicate names case-insensitively, including the creator's name, with indexed member_names validation errors and no partial group or guest records. The create form accepts names one at a time with an Add member button and a removable list. Ignore blank input and include any remaining nonblank name when creating the group. Initial members need no emails or accounts. Example: {"name":"Trip","currency":"MYR","member_names":["Ali","Abu"]}.

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

Single-resource responses use {"data": resource}; collection responses use {"data": [resources]}. Register/login/me return {"data": {"id": "...", "name": "...", "email": "..."}}. Groups expose id, name, created_by, currency and timestamps. Member resources expose id (participant identity ULID), name, nullable email, guest (boolean), role and active. Registered auth user responses still require a string email. Guest identity IDs remain valid paid_by and split user_id values. Summary uses {"data": {"currency": "MYR", "total_expenses": "210.00", "members": [{"id": "...", "name": "...", "paid_total": "...", "share_total": "...", "receivable_total": "...", "payable_total": "...", "net_balance": "..."}]}}.

The settlement endpoint intentionally keeps the existing top-level {"currency": "MYR", "settlements": [{"from": {"id": "...", "name": "..."}, "to": {"id": "...", "name": "..."}, "amount": "..."}]} contract from 08-api.md. Each entry describes a required payment from debtor to creditor.

Use 200 for reads/updates/login/password changes, 201 for creates/register, 204 with no JSON body for logout/deletes; 401 unauthenticated, 403 unauthorized, 404 missing or wrong-group resource, 422 validation and 429 throttled. Validation errors use the existing {message, errors} shape; other errors have message. Handle session/CSRF expiry with actionable frontend feedback.

Member responses additionally include nullable account_user_id (the owner account ID for owners, or the invited account ID for linked members). guest is false for a linked member. Auth user IDs and participant IDs can differ: frontend maps the session account through account_user_id for personal balances, payer defaults, '(you)' labels and expense authorship. Backend amounts and settlement IDs remain participant-based.

GET expenses accepts sort=date|amount|description and direction=asc|desc; default date descending, with ID as a stable tie-breaker. No pagination is required for the small-group MVP. GET members includes active and former members; GET groups lists created groups with active owner membership and joined groups with active linked membership. Invalid, expired and revoked invitation tokens return 404; claimed/already-linked conflicts return 422; unauthenticated requests return 401.

## Corrections to source examples

- Source section 32 had a reversed Amir/Ali result. Dinner 120 paid by Amir, Grab 60 paid by Ali and Drinks 30 paid by Abu produce **Ali → Amir 20.00**, **Abu → Amir 30.00**, **Abu → Ali 10.00**. The split files correct both occurrences.
- Source section 13 said both Ali and Abu pay each other 25.00. With its stated example debts, Abu **receives** 25.00 from Ali. The UI example is corrected.
- UI examples in separate sections describe independent illustrative scenarios. Do not combine their figures into one fixture. Test the exact three-expense fixture above separately from seed data that also includes Hotel.
- Invitation links and account claims are now explicitly requested scope. Percentage splits, settlement history, "mark paid" and PWA remain deferred. Named participants, invited accounts, equal/custom splits and read-only pairwise settlements form the current scope.
