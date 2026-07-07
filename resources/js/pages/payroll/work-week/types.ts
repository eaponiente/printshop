export type DayCellData = {
    is_present: boolean;
    is_rest_day: boolean;
    absence_type: string | null;
    holiday_type: string | null;
    holiday_pay_percent: number | null;
    leave_type: string | null;
    leave_duration: string | null;
    late_minutes: number;
    overtime_minutes: number;
    hours_worked: number;
    daily_wage: number;
    fine_deduction: number;
};

export type EmployeeRowTotals = {
    employee_id: number;
    employee_number: string;
    full_name: string;
    daily_rate: number;
    fine_deduction: number;
    late_minutes: number;
    overtime_minutes: number;
    overtime_hours: number;
    holiday_count: number;
    cash_advance: number;
    sss_deduction: number;
    philhealth_deduction: number;
    pagibig_deduction: number;
    total_deductions: number;
    gross_pay: number;
    net_pay: number;
};

export type FooterTotals = {
    gross_payroll: number;
    total_deductions: number;
    total_net_salary: number;
    total_cash_advance: number;
    total_overtime_hours: number;
    total_holidays: number;
    total_lates: number;
};

export type BranchOption = {
    id: number;
    name: string;
};

export type EmployeeListItem = {
    id: number;
    employee_number: string;
    full_name: string;
};
