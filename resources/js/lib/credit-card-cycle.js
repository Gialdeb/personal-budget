function buildValidDate(year, monthIndex, day) {
    const lastDayOfMonth = new Date(year, monthIndex + 1, 0).getDate();

    return new Date(year, monthIndex, Math.min(day, lastDayOfMonth));
}

function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

export function resolveCreditCardCycle(referenceDate, closingDay, paymentDay) {
    if (
        !Number.isInteger(closingDay) ||
        closingDay < 1 ||
        closingDay > 31 ||
        !Number.isInteger(paymentDay) ||
        paymentDay < 1 ||
        paymentDay > 31
    ) {
        return null;
    }

    const reference = startOfDay(referenceDate);
    const currentMonthClosing = buildValidDate(
        reference.getFullYear(),
        reference.getMonth(),
        closingDay,
    );
    const periodEnd =
        currentMonthClosing >= reference
            ? currentMonthClosing
            : buildValidDate(
                  reference.getFullYear(),
                  reference.getMonth() + 1,
                  closingDay,
              );

    const previousClosing = buildValidDate(
        periodEnd.getFullYear(),
        periodEnd.getMonth() - 1,
        closingDay,
    );
    const currentPeriodStart = new Date(previousClosing);
    currentPeriodStart.setDate(currentPeriodStart.getDate() + 1);

    const paymentMonthOffset = paymentDay > closingDay ? 0 : 1;
    const nextPaymentDate = buildValidDate(
        periodEnd.getFullYear(),
        periodEnd.getMonth() + paymentMonthOffset,
        paymentDay,
    );

    return {
        current_period_start: currentPeriodStart,
        current_period_end: periodEnd,
        next_payment_date: nextPaymentDate,
    };
}
