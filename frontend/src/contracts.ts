// Shared wire interfaces owned by personal-expense-debt-spec/03-shared-contracts.md.
export type ID = string;
export type Money = string;
export interface User { id: ID; name: string; email: string }
export interface Group { id: ID; name: string; created_by: ID; currency: 'MYR'; created_at: string; updated_at: string }
export interface Member extends Omit<User, 'email'> { email: string | null; guest: boolean; account_user_id: ID | null; role: 'owner' | 'member'; active: boolean }
export interface Invitation { group: Pick<Group, 'id' | 'name'>; members: Pick<Member, 'id' | 'name'>[] }
export interface Split { user_id: ID; amount: Money }
export interface Expense {
  id: ID; group_id: ID; created_by: ID; description: string; amount: Money;
  paid_by: ID; split_type: 'equal' | 'custom'; expense_date: string; notes: string | null;
  created_at: string; updated_at: string; splits: Split[];
}
export interface ExpenseInput {
  description: string; amount: Money; paid_by: ID; split_type: 'equal' | 'custom';
  expense_date: string; notes: string | null; participant_ids: ID[]; splits?: Split[];
}
export interface MemberTotal {
  id: ID; name: string; paid_total: Money; share_total: Money;
  receivable_total: Money; payable_total: Money; net_balance: Money;
}
export interface Summary { currency: 'MYR'; total_expenses: Money; members: MemberTotal[] }
export interface Settlement { from: Pick<User, 'id' | 'name'>; to: Pick<User, 'id' | 'name'>; amount: Money }
export interface Settlements { currency: 'MYR'; settlements: Settlement[] }
export interface Data<T> { data: T }
