# Authentication, groups and authorization

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 20: Authentication

Only group creators need accounts and authentication. Named participants require neither registration nor login.

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

The create-group form includes optional member names, added one at a time, and automatically includes the creator. Save the group and initial participants atomically. The creator can also add participants later by name, with an optional contact email. No registered account lookup, linking or invitation occurs. Participants never need to log in. Removing and re-adding a name preserves historical identity and debts; see 03-shared-contracts.md.

---

## Source section 22: Group Permissions

Basic roles:

```text
owner
member
```

Only the original group creator can:

- View group, members, expenses, summary and settlements
- Rename group
- Add/remove members
- Delete group
- Manage expenses

Named participants can be selected as payers and included in equal/custom expense shares. They have no app access or mutation permissions. The creator records expenses even when someone else paid. Existing registered members also lose access to groups created by someone else.

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

Authenticated users must only be able to access groups they created. Being a participant or matching an optional email must never grant access.

Users must not be able to modify another group's data by changing an ID in the request.

Use authorization policies.
