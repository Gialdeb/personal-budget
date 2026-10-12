export type SalaryAdditionalSchedule = {
    label: string;
    amount: string;
    start_date: string;
    recurrence_type: string;
    recurrence_interval: number;
    account_uuid: string;
};
export function salaryPresetSchedules(
    preset: string,
    startDate: string,
    amount: string,
    labels: string[],
): SalaryAdditionalSchedule[] {
    const months = preset === '14' ? [12, 6] : preset === '13' ? [12] : [];

    return months.map((month, index) => {
        let year = Number(startDate.slice(0, 4));
        let date = `${year}-${String(month).padStart(2, '0')}-01`;

        if (date < startDate) {
            year++;
            date = `${year}-${String(month).padStart(2, '0')}-01`;
        }

        return {
            label: labels[index],
            amount,
            start_date: date,
            recurrence_type: 'yearly',
            recurrence_interval: 1,
            account_uuid: '',
        };
    });
}
export type SalarySetup = {
    name: string;
    employment_uuid: string;
    kind: string;
    annual_gross_salary: string;
    annual_gross_salary_currency: string;
    additional_schedules: SalaryAdditionalSchedule[];
};
export function emptySalarySetup(): SalarySetup {
    return {
        name: '',
        employment_uuid: '',
        kind: 'ordinary',
        annual_gross_salary: '',
        annual_gross_salary_currency: 'EUR',
        additional_schedules: [],
    };
}
