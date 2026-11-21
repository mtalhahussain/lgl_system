// Fit padded branding uploads without changing the stored image.
export function initLoginLogo() {
    document.querySelectorAll('img.login-uploaded-logo, .sidebar-brand .portal-brand-expanded img.portal-brand-logo, img.apply-brand-logo, .receipt-brand img.document-brand-logo').forEach(fitLogo);
}

function fitLogo(image) {

    const fit = () => {
        if (!image.naturalWidth || !image.naturalHeight || !image.isConnected) return;
        try {
            const scale = Math.min(1, 800 / Math.max(image.naturalWidth, image.naturalHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
            const context = canvas.getContext('2d', { willReadFrequently: true });
            if (!context) return;
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            const { data } = context.getImageData(0, 0, canvas.width, canvas.height);
            let left = canvas.width, top = canvas.height, right = -1, bottom = -1;
            for (let y = 0; y < canvas.height; y++) {
                for (let x = 0; x < canvas.width; x++) {
                    const i = (y * canvas.width + x) * 4;
                    if (data[i + 3] < 24 || (data[i] > 240 && data[i + 1] > 240 && data[i + 2] > 240)) continue;
                    left = Math.min(left, x); right = Math.max(right, x);
                    top = Math.min(top, y); bottom = Math.max(bottom, y);
                }
            }
            if (right < left || bottom < top) return;
            const padding = Math.max(2, Math.round((right - left + 1) * 0.015));
            left = Math.max(0, left - padding); top = Math.max(0, top - padding);
            right = Math.min(canvas.width, right + padding + 1);
            bottom = Math.min(canvas.height, bottom + padding + 1);
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', image.className.baseVal ?? image.className);
            svg.setAttribute('viewBox', `${left / scale} ${top / scale} ${(right - left) / scale} ${(bottom - top) / scale}`);
            svg.setAttribute('width', (right - left) / scale);
            svg.setAttribute('height', (bottom - top) / scale);
            svg.setAttribute('role', 'img');
            svg.setAttribute('aria-label', image.alt);
            const content = document.createElementNS('http://www.w3.org/2000/svg', 'image');
            content.setAttribute('href', image.currentSrc || image.src);
            content.setAttribute('width', image.naturalWidth);
            content.setAttribute('height', image.naturalHeight);
            svg.append(content);
            image.replaceWith(svg);
        } catch {
            // Cross-origin or unreadable uploads retain the normal image fallback.
        }
    };
    if (image.complete) fit();
    else image.addEventListener('load', fit, { once: true });
}
