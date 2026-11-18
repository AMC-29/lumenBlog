document.addEventListener('DOMContentLoaded', () => {
    const maxBytes = 4 * 1024 * 1024;
    const inputs = document.querySelectorAll('[data-image-input]');
    const forms = new Map();

    inputs.forEach(input => {
        const preview = document.getElementById(input.dataset.previewTarget);
        const error = document.createElement('p');
        error.className = 'upload-error';
        error.setAttribute('aria-live', 'polite');
        input.insertAdjacentElement('afterend', error);
        if (input.form) forms.set(input.form, [...(forms.get(input.form) || []), input]);

        input.addEventListener('change', () => {
            error.textContent = '';
            const file = input.files && input.files[0];
            if (!file) return;
            if (file.size > maxBytes) {
                error.textContent = 'Images must be no larger than 4 MB.';
                input.value = '';
                if (preview) preview.replaceChildren();
                return;
            }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                error.textContent = 'Choose a JPEG, PNG, or WebP image.';
                input.value = '';
                if (preview) preview.replaceChildren();
                return;
            }
            if (preview) {
                preview.replaceChildren();
                const image = document.createElement('img');
                image.alt = 'Selected image preview';
                image.src = URL.createObjectURL(file);
                image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });
                preview.appendChild(image);
            }
        });
    });

    forms.forEach((fileInputs, form) => {
        form.addEventListener('submit', event => {
            if (fileInputs.some(input => input.files && input.files[0] && input.files[0].size > maxBytes)) {
                event.preventDefault();
                fileInputs.find(input => input.files && input.files[0] && input.files[0].size > maxBytes)?.focus();
            }
        });
    });

    const tagsInput = document.getElementById('tagInput');
    if (tagsInput) {
        document.querySelectorAll('[data-tag]').forEach(button => {
            button.addEventListener('click', () => {
                const tag = button.dataset.tag || '';
                const current = tagsInput.value.split(',').map(value => value.trim()).filter(Boolean);
                if (!current.some(value => value.toLocaleLowerCase() === tag.toLocaleLowerCase())) {
                    current.push(tag);
                    tagsInput.value = current.join(', ');
                    tagsInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                tagsInput.focus();
            });
        });
    }
});
