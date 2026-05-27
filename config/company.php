<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Special Group Branches
    |--------------------------------------------------------------------------
    |
    | Branches in this array share access with each other. An admin assigned to
    | any of these branches can view and manage employees, attendance sheets,
    | and payroll data belonging to all other branches in this group.
    |
    | Used by EmployeePolicy, AttendanceSheetPolicy, and other payroll policies.
    |
    */
    'special_group_branch_names' => ['Babak', 'Peñaplata', 'Tibungco'],
];
