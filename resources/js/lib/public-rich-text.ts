const ALLOWED_TAGS = new Set([
    'p',
    'br',
    'h2',
    'h3',
    'figure',
    'figcaption',
    'ul',
    'ol',
    'li',
    'strong',
    'b',
    'em',
    'i',
    'a',
    'img',
]);

export function sanitizePublicRichText(
    html: string | null | undefined,
): string {
    if (!html) {
        return '';
    }

    let sanitized = html
        .replace(
            /<\s*(script|style|iframe|object|embed)[^>]*>[\s\S]*?<\s*\/\s*\1>/gi,
            '',
        )
        .replace(/\son[a-z]+\s*=\s*(['"]).*?\1/gi, '')
        .replace(/\sstyle\s*=\s*(['"]).*?\1/gi, '');

    sanitized = sanitized.replace(
        /<(\/?)([a-z0-9-]+)([^>]*)>/gi,
        (match, slash: string, tagName: string, attributes: string) => {
            const normalizedTag = String(tagName).toLowerCase();

            if (!ALLOWED_TAGS.has(normalizedTag)) {
                return '';
            }

            if (slash === '/') {
                return `</${normalizedTag}>`;
            }

            if (normalizedTag === 'img') {
                const srcMatch = attributes.match(/\ssrc\s*=\s*(['"])(.*?)\1/i);
                const altMatch = attributes.match(/\salt\s*=\s*(['"])(.*?)\1/i);
                const srcValue = srcMatch?.[2]?.trim() ?? '';
                const safeSrc =
                    /^(https?:\/\/|\/storage\/|\/editorial-assets(?:[/?]|$))/i.test(
                        srcValue,
                    ) && !/^javascript:/i.test(srcValue)
                        ? srcValue
                        : '';

                if (!safeSrc) {
                    return '';
                }

                const altValue = (altMatch?.[2] ?? '').replace(/"/g, '&quot;');

                return `<img src="${safeSrc}" alt="${altValue}" loading="lazy">`;
            }

            if (normalizedTag !== 'a') {
                return `<${normalizedTag}>`;
            }

            const hrefMatch = attributes.match(/\shref\s*=\s*(['"])(.*?)\1/i);
            const hrefValue = hrefMatch?.[2]?.trim() ?? '#';
            const safeHref = /^javascript:/i.test(hrefValue) ? '#' : hrefValue;

            return `<a href="${safeHref}" target="_blank" rel="noopener noreferrer">`;
        },
    );

    return sanitized.trim();
}
