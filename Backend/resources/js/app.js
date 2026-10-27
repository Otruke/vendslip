// NetSlip admin: tiny progressive enhancement (no framework).
// data-open="id"   → opens <dialog id="id"> (modals & drawers)
// data-close       → closes the closest dialog
// data-confirm="…" → asks before submitting a form
// data-copy="text" → copies to clipboard
document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    const opener = target.closest('[data-open]');
    if (opener) {
        const dialog = document.getElementById(opener.getAttribute('data-open'));
        if (dialog instanceof HTMLDialogElement) {
            event.preventDefault();
            dialog.showModal();
        }
        return;
    }

    if (target.closest('[data-close]')) {
        target.closest('dialog')?.close();
        return;
    }

    const copier = target.closest('[data-copy]');
    if (copier) {
        navigator.clipboard?.writeText(copier.getAttribute('data-copy') ?? '');
        const original = copier.textContent;
        copier.textContent = 'Copied';
        setTimeout(() => { copier.textContent = original; }, 1200);
    }
});

// Click on the backdrop closes a dialog.
document.addEventListener('click', (event) => {
    if (event.target instanceof HTMLDialogElement) event.target.close();
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
        return;
    }
    // Prevent double submits.
    if (form instanceof HTMLFormElement) {
        form.querySelectorAll('button[type="submit"]').forEach((b) => {
            b.setAttribute('disabled', 'disabled');
            b.classList.add('opacity-60');
        });
    }
});

// ── Floating toasts ────────────────────────────────────────────────────────────
// Auto-dismiss after data-toast-ms; hovering or focusing a toast pauses the countdown.
function dismissToast(toast) {
    if (!(toast instanceof HTMLElement) || toast.hasAttribute('data-leaving')) return;
    toast.setAttribute('data-leaving', '');
    const remove = () => toast.remove();
    toast.addEventListener('animationend', remove, { once: true });
    setTimeout(remove, 400); // fallback when animations are disabled
}

function armToast(toast) {
    let remaining = Number(toast.getAttribute('data-toast-ms')) || 5200;
    let startedAt = Date.now();
    let timer = setTimeout(() => dismissToast(toast), remaining);
    const pause = () => { clearTimeout(timer); remaining -= Date.now() - startedAt; };
    const resume = () => { startedAt = Date.now(); clearTimeout(timer); timer = setTimeout(() => dismissToast(toast), Math.max(remaining, 600)); };
    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', resume);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', resume);
}

document.querySelectorAll('[data-toast]').forEach(armToast);

document.addEventListener('click', (event) => {
    const close = event.target instanceof Element ? event.target.closest('[data-toast-close]') : null;
    if (close) dismissToast(close.closest('[data-toast]'));
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || document.querySelector('dialog[open]')) return;
    const toasts = document.querySelectorAll('[data-toast]:not([data-leaving])');
    if (toasts.length) dismissToast(toasts[toasts.length - 1]);
});
