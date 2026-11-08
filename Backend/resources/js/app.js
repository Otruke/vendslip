// VendSlip admin: tiny progressive enhancement (no framework).
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

// ── File inputs that submit their form as soon as a file is picked (profile photo) ──
document.addEventListener('change', (event) => {
    const input = event.target;
    if (input instanceof HTMLInputElement && input.type === 'file' && input.hasAttribute('data-autosubmit') && input.files?.length) {
        input.form?.requestSubmit();
    }
});

// Small pop-overs built on <details data-close-outside> close on an outside tap or Escape.
document.addEventListener('pointerdown', (e) => {
    document.querySelectorAll('details[data-close-outside][open]').forEach((d) => { if (!d.contains(e.target)) d.removeAttribute('open'); });
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelectorAll('details[data-close-outside][open]').forEach((d) => d.removeAttribute('open'));
});

// ── "i" info tips: hover/focus on desktop, tap to pin on phones; tap away or Escape closes ──
let tip = null;
let tipOwner = null;
let tipPinned = false;
function placeTip() {
    if (!tip || !tipOwner) return;
    const r = tipOwner.getBoundingClientRect();
    const b = tip.getBoundingClientRect();
    const left = Math.min(Math.max(12, r.left + r.width / 2 - b.width / 2), window.innerWidth - b.width - 12);
    const above = r.bottom + b.height + 12 > window.innerHeight && r.top > b.height + 12;
    tip.style.left = `${left}px`;
    tip.style.top = `${above ? r.top - b.height - 8 : r.bottom + 8}px`;
}
function hideTip() {
    tip?.remove();
    tipOwner?.setAttribute('aria-expanded', 'false');
    tipOwner?.removeAttribute('aria-describedby');
    tip = null; tipOwner = null; tipPinned = false;
}
function showTip(owner) {
    if (tipOwner === owner) return;
    hideTip();
    const body = owner.querySelector('[data-tip-body]');
    if (!body) return;
    tip = document.createElement('div');
    tip.className = 'vs-tip';
    tip.id = 'vs-tip';
    tip.setAttribute('role', 'tooltip');
    tip.textContent = body.textContent.trim();
    document.body.append(tip);
    tipOwner = owner;
    owner.setAttribute('aria-expanded', 'true');
    owner.setAttribute('aria-describedby', 'vs-tip');
    placeTip();
}
document.addEventListener('click', (e) => {
    const owner = e.target instanceof Element ? e.target.closest('[data-tip]') : null;
    if (!owner) return;
    e.preventDefault();
    if (tipOwner === owner && tipPinned) { hideTip(); return; }
    showTip(owner);
    tipPinned = true;
});
document.addEventListener('pointerover', (e) => {
    const owner = e.pointerType === 'mouse' && e.target instanceof Element ? e.target.closest('[data-tip]') : null;
    if (owner && !tipPinned) showTip(owner);
});
document.addEventListener('pointerout', (e) => {
    const owner = e.pointerType === 'mouse' && e.target instanceof Element ? e.target.closest('[data-tip]') : null;
    if (owner && owner === tipOwner && !tipPinned && !owner.contains(e.relatedTarget)) hideTip();
});
document.addEventListener('focusin', (e) => { if (e.target instanceof Element && e.target.matches('[data-tip]')) showTip(e.target); });
document.addEventListener('focusout', (e) => { if (e.target === tipOwner && !tipPinned) hideTip(); });
document.addEventListener('pointerdown', (e) => { if (tipPinned && tipOwner && !tipOwner.contains(e.target) && !tip?.contains(e.target)) hideTip(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && tip) hideTip(); });
window.addEventListener('scroll', placeTip, true);
window.addEventListener('resize', placeTip);
