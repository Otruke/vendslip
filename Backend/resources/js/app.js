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
