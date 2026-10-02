# Authentication, groups and authorization

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 20: Authentication

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
Leave group
```

For the MVP, the owner adds existing registered users by email. Invite links are a future feature. See the membership lifecycle in 03-shared-contracts.md.

---

## Source section 22: Group Permissions

Basic roles:

```text
owner
member
```

Owner can:

- Rename group
- Add/remove members
- Delete group
- Manage expenses

Members can:

- View group
- Add expenses
- Edit their own expenses
- View settlements

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

Users must only be able to access groups they belong to.

Users must not be able to modify another group's data by changing an ID in the request.

Use authorization policies.
