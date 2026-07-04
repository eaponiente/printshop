import type { Branch } from '@/types/branches';
import type { PaginatedResponse } from '@/types/pagination';

export type EmployeeSchedule = {
    id: number;
    employee_id: number;
    start_time: string;
    end_time: string;
    unpaid_tail_minutes: number;
    rest_days: number[];
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
};

export type Employee = {
    id: number;
    employee_number: string;
    branch_id: number;
    first_name: string;
    last_name: string;
    middle_name: string | null;
    full_name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    birth_date: string | null;
    hire_date: string;
    end_date: string | null;
    position: 'regular' | 'probation';
    status: 'active' | 'inactive' | 'resigned' | 'terminated';
    current_daily_rate: number;
    default_paid_leave_days: number;
    paid_leave_balance: number;
    can_edit_sewed_items: boolean;
    sss_number: string | null;
    philhealth_number: string | null;
    pagibig_number: string | null;
    tin_number: string | null;
    sss_deduction_per_week: number;
    philhealth_deduction_per_week: number;
    pagibig_deduction_per_week: number;
    notes: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    branch: Branch;
    salaries?: Salary[];
    active_schedule?: EmployeeSchedule | null;
    user?: {
        id: number;
        username: string;
        role: 'admin' | 'staff' | 'superadmin';
    } | null;
    [key: string]: unknown;
};

export type Salary = {
    id: number;
    employee_id: number;
    daily_rate: number;
    effective_date: string;
    end_date: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
};

export type EmployeesList = {
    employees: PaginatedResponse<Employee>;
    statuses: Array<{ key: string; value: string }>;
    positions: Array<{ key: string; value: string }>;
    branches: Branch[];
    filterColumns: Array<{ key: string; value: string }>;
    filters: Array<{ column: string; value: string }>;
    isSuperAdmin: boolean;
};
