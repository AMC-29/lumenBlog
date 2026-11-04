// ==========================================================
// Lumen — single post view behaviors
// ==========================================================
document.addEventListener('DOMContentLoaded', () => {
    initDeleteModal();
    initReadingProgress();
});

function initDeleteModal() {
    const deleteBtn = document.getElementById('deleteBtn');
    const modal = document.getElementById('deleteModal');
    const cancelBtn = document.getElementById('modalCancel');
    const idField = document.getElementById('deleteId');
    const modalText = document.getElementById('modalText');
    if (!deleteBtn || !modal) return;

    deleteBtn.addEventListener('click', () => {
        idField.value = deleteBtn.dataset.id;
        modalText.textContent = `“${deleteBtn.dataset.title}” will be permanently removed. This can't be undone.`;
        modal.hidden = false;
    });

    const close = () => { modal.hidden = true; };
    cancelBtn.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) close(); });
}

/* Fills the horizon bar at the top of the page as the reader scrolls the article. */
function initReadingProgress() {
    const bar = document.querySelector('.horizon-bar');
    const article = document.querySelector('.post-content');
    if (!bar || !article) return;

    bar.dataset.progress = 'true';

    const update = () => {
        const rect = article.getBoundingClientRect();
        const total = rect.height - window.innerHeight;
        const scrolled = Math.min(Math.max(-rect.top, 0), total);
        const pct = total > 0 ? (scrolled / total) * 100 : 0;
        bar.style.setProperty('--progress', pct + '%');
    };

    update();
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
}
