export function calendarTooltipPosition(anchor, box, viewport) {
    const left = Math.max(12, Math.min(viewport.width - box.width - 12, anchor.left + (anchor.width - box.width) / 2));
    const below = anchor.bottom + 10;
    const top = below + box.height <= viewport.height - 12 ? below : Math.max(12, anchor.top - box.height - 10);
    return {left, top};
}

// Keep calendar summaries outside the horizontal scroll container so they never clip.
export function initCalendarTooltips() {
    let active = null;
    let hideTimer;
    const hide = () => {
        clearTimeout(hideTimer);
        active?.tooltip.classList.remove('is-visible');
        active = null;
    };
    document.querySelectorAll('[data-calendar-cell]').forEach(cell => {
        const tooltip = cell.querySelector('[role="tooltip"]');
        if (!tooltip) return;
        document.body.append(tooltip);
        tooltip.style.position = 'fixed';
        const show = () => {
            clearTimeout(hideTimer);
            if (active?.tooltip !== tooltip) hide();
            const anchor = cell.getBoundingClientRect();
            const box = tooltip.getBoundingClientRect();
            const {left, top} = calendarTooltipPosition(anchor, box, {width: window.innerWidth, height: window.innerHeight});
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
            tooltip.classList.add('is-visible');
            active = {cell, tooltip};
        };
        cell.addEventListener('pointerenter', show);
        cell.addEventListener('focusin', show);
        cell.addEventListener('pointerleave', () => {
            if (!cell.contains(document.activeElement)) hideTimer = setTimeout(hide, 70);
        });
        cell.addEventListener('focusout', event => {
            if (!cell.contains(event.relatedTarget)) hide();
        });
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') hide(); });
    document.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);
}
