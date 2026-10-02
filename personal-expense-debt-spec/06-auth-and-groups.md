# Authentication, groups and authorization

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 20: Authentication

Creators and invited participants can register and log in. Named participants can remain without accounts until they choose to join through an invitation.

MVP authentication:

- Register
- Login
- Logout
- Current user
- Change password

Use Laravel Sanctum.

API endpoints:

```text
POST /api/auth/register
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/me
```

Do not implement social login in the MVP.

---

## Source section 21: Group Management

Users should be able to:

```text
Create group
View groups
Rename group
Add members
Remove members
```

The create-group form includes optional member names, added one at a time, and automatically includes the creator. Save the group and initial participants atomically. The creator can also add participants later by name, with an optional contact email. No account lookup or linking occurs when merely adding a name/contact email. Participants may later use a creator-issued invitation to link their own account. Removing and re-adding a name preserves historical identity and debts; see 03-shared-contracts.md.

---

## Source section 22: Group Permissions

Basic roles:

```text
owner
member
```

Only the original group creator can:

- Rename group
- Add/remove members
- Generate/replace/revoke invitations
- Delete group
- Delete expenses

Named participants can be selected as payers and included in equal/custom expense shares. After joining by invitation and selecting an unclaimed name, active linked accounts can view the group and add/edit all expenses. The selected member's contact email becomes the account email, while historical identity and debts stay unchanged. Existing registered membership alone grants no access. See 03-shared-contracts.md and ../docs/invitations.md for invitation lifecycle and claim rules.

Keep authorization simple.

---

## Source section 37: Security

Implement:

- Password hashing
- Authentication
- Authorization
- Request validation
- CSRF protection where applicable
- API authentication
- SQL injection protection through Laravel ORM/query builder
- Rate limiting for authentication endpoints

Authenticated users can access groups they created or explicitly joined through an invitation while their membership is active. Being a named participant or matching an optional email alone never grants access.

Users must not be able to modify another group's data by changing an ID in the request.

Use authorization policies.
