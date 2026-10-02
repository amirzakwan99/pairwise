# REST API

Read [README.md](README.md) first. Shared interfaces and resolved decisions are defined in [03-shared-contracts.md](03-shared-contracts.md). Original section numbers are retained for traceability.

## Source section 19: Settlement Calculation API

Provide an endpoint similar to:

```http
GET /api/groups/{group}/settlements
```

Example response:

```json
{
  "currency": "MYR",
  "settlements": [
    {
      "from": {
        "id": "user-1",
        "name": "Amir"
      },
      "to": {
        "id": "user-2",
        "name": "Ali"
      },
      "amount": "30.00"
    },
    {
      "from": {
        "id": "user-1",
        "name": "Amir"
      },
      "to": {
        "id": "user-3",
        "name": "Abu"
      },
      "amount": "20.00"
    }
  ]
}
```

The backend should return only non-zero pairwise debts.

---

## Source section 29: API Design

Use RESTful endpoints.

Example:

```text
GET    /api/groups
POST   /api/groups

GET    /api/groups/{group}
PATCH  /api/groups/{group}
DELETE /api/groups/{group}

GET    /api/groups/{group}/members
POST   /api/groups/{group}/members
DELETE /api/groups/{group}/members/{user}

GET    /api/groups/{group}/expenses
POST   /api/groups/{group}/expenses

GET    /api/groups/{group}/expenses/{expense}
PATCH  /api/groups/{group}/expenses/{expense}
DELETE /api/groups/{group}/expenses/{expense}

GET    /api/groups/{group}/settlements
```

Only the authenticated creator can use the group, member, expense and settlement routes. POST members requires name and accepts optional nullable email. Contact email does not grant access or link an account. There is no participant leave endpoint.

Use consistent JSON response formats.

Use proper HTTP status codes.

---

## Source section 36: Error Handling

Use consistent API errors.

Example:

```json
{
  "message": "The expense splits must equal the expense amount.",
  "errors": {
    "splits": [
      "The total split amount must equal RM120.00."
    ]
  }
}
```

Frontend should show friendly validation messages.

Do not expose stack traces or sensitive server information.
