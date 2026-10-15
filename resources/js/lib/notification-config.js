/** @type {import('notivue').NotivueConfig} */
export const notificationConfig = {
    position: 'top-right',
    limit: 3,
    enqueue: true,
    avoidDuplicates: true,
    pauseOnHover: true,
    pauseOnTouch: true,
    pauseOnTabChange: true,
    notifications: {
        global: { duration: 6000 },
        warning: { duration: 10000 },
        error: { duration: 10000 },
    },
};
