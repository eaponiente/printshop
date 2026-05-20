import type { Branch } from '@/types/branches';
import type { PaginatedResponse } from '@/types/pagination';

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
    position: 'regular' | 'contractual' | 'project_based';
    status: 'active' | 'resigned' | 'terminated';
    current_daily_rate: number;
    sss_number: string | null;
    philhealth_number: string | null;
    pagibig_number: string | null;
    tin_number: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    branch: Branch;
    salaries?: Salary[];
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
};
