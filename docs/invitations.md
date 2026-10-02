# Invited member accounts

Run `php artisan migrate` when updating an existing installation. The additive migration adds invitation hash/expiry fields and a nullable `group_members.account_user_id` with a unique constraint per group. Existing participant IDs, expenses and shares are preserved. No new packages, email service or background worker are required.

1. The creator adds participant names, either while creating the group or on the Members tab.
2. On Members, the creator selects **Create invitation link** and shares the displayed URL with trusted participants. Links expire after seven days. **Replace invitation link** invalidates the old URL; **Revoke invitation** prevents further joins. The raw token is shown only when generated and is not stored in the database.
3. An invitee opens the URL and signs in or registers. Authentication preserves the invitation destination.
4. The invitee selects their existing name and selects **Join group**. The membership email becomes the account's login email. The group name and historic participant ID stay unchanged, so saved expenses and debts remain intact.
5. Joined active members see the group under Your groups and can add/edit any expense. Only the creator manages participants/invitations/group settings and deletes expenses or the group.

Each account can claim only one name in a group, and each name can be claimed only once. Owners, former participants and identities already tied to login credentials are excluded from the selection. Missing names must be added by the creator. Claims and invitation changes use the same group lock as expense writes. Matching a contact email or name never grants membership automatically.

Anyone with a valid link and an account can select an available name. Share links only with trusted group participants. No invitation emails are sent. Removing a linked participant revokes access immediately while preserving history; re-adding their name restores the existing account link. Revoking a link does not remove members who already joined.

Guest identity credentials remain null. Account authentication continues to use the registered user row; `account_user_id` links it to the participant identity. The frontend uses this mapping for personal balances, default payer selection and author names. Backend calculations still use exact cents and net debts only within each pair.
