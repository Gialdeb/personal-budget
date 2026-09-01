export function selectedMonthScrollLeft(
    monthCenter: number,
    viewportWidth: number,
    contentWidth: number,
): number {
    const maximumScroll = Math.max(0, contentWidth - viewportWidth);

    return Math.min(
        maximumScroll,
        Math.max(0, monthCenter - viewportWidth / 2),
    );
}

type TimelineClickParams = {
    componentType?: string;
    dataIndex?: number;
};

export function resolveTimelineClickPoint<T>(
    params: TimelineClickParams,
    points: T[],
): T | null {
    if (
        params.componentType !== 'series' ||
        typeof params.dataIndex !== 'number'
    ) {
        return null;
    }

    return points[params.dataIndex] ?? null;
}
