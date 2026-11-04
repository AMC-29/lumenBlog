document.addEventListener('DOMContentLoaded', () => {
    initNavToggle();
    initToasts();
    initScrollReveal();
    clearSavedEditorDraft();
});

function clearSavedEditorDraft() {
    if (typeof window.__clearEditorDraft !== 'string') return;
    try {
        localStorage.removeItem('lumen-draft-' + window.__clearEditorDraft);
    } catch (e) { /* localStorage unavailable — nothing to clear */ }
}

/* ---------- Mobile nav toggle ---------- */
function initNavToggle() {
    const toggle = document.getElementById('navToggle');
    const nav = document.getElementById('siteNav');
    if (!toggle || !nav) return;

    toggle.addEventListener('click', () => {
        const isOpen = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    // Close the menu when a link inside it is clicked (mobile UX nicety)
    nav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            nav.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    });
}

/* ---------- Flash message toasts ---------- */
function initToasts() {
    const stack = document.getElementById('toastStack');
    if (!stack || !Array.isArray(window.__flashes)) return;

    window.__flashes.forEach((f, i) => {
        setTimeout(() => showToast(f.message, f.type), i * 150);
    });
}

function showToast(message, type = 'success') {
    const stack = document.getElementById('toastStack');
    if (!stack) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    stack.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('leaving');
        setTimeout(() => toast.remove(), 300);
    }, 4200);
}

/* ---------- Scroll-reveal for post cards ---------- */
function initScrollReveal() {
    const cards = document.querySelectorAll('.reveal');
    if (!cards.length) return;

    if (!('IntersectionObserver' in window)) {
        cards.forEach(c => c.classList.add('revealed'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    cards.forEach(card => observer.observe(card));
}
