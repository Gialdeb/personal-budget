// Only open an existing editor; submission stays with the ordinary recurrence flow.
export function requestedOccurrenceAction(url, isCompound, occurrence) {
    if (!isCompound || !occurrence) {
        return null;
    }

    const query = new URLSearchParams(url.split('?')[1] ?? '');

    if (query.get('highlight') !== occurrence.uuid) {
        return null;
    }

    const action = query.get('occurrence_action');
    const permission = {
        date: 'can_update_occurrence',
        amount: 'can_update_amount',
        payment: 'can_register_payment',
    }[action];

    return permission && occurrence[permission] === true ? action : null;
}

export function canCreateCompoundRecurring({
    editing,
    reactivation,
    salary,
    planType,
    recurrenceType,
    interval,
}) {
    return (
        !editing &&
        !reactivation &&
        !salary &&
        planType === 'recurring' &&
        recurrenceType === 'yearly' &&
        Number(interval) === 1
    );
}

export function compoundRecurringCreationPayload(data, components) {
    return {
        name: data.description.trim().slice(0, 150),
        account_uuid: data.account_uuid,
        category_uuid: data.category_uuid,
        direction: data.direction,
        reminder_days_before: [...data.reminder_days_before],
        components: components.map((component) => ({ ...component })),
    };
}

export function withFirstCompoundPayment(payments, date, amount) {
    const [first = { name: '', date: '', amount: '' }, ...remaining] = payments;

    return [{ ...first, date, amount }, ...remaining];
}
