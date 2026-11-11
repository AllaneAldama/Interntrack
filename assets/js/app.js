document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-target]');
    if (opener) {
        const dialog = document.getElementById(opener.dataset.modalTarget);
        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
        }
    }

    const closer = event.target.closest('[data-modal-close]');
    if (closer) {
        const dialog = closer.closest('dialog');
        if (dialog instanceof HTMLDialogElement) {
            dialog.close();
        }
    }
});

document.addEventListener('click', (event) => {
    if (event.target instanceof HTMLDialogElement && event.target.open) {
        const bounds = event.target.getBoundingClientRect();
        const clickedOutside = event.clientX < bounds.left || event.clientX > bounds.right
            || event.clientY < bounds.top || event.clientY > bounds.bottom;
        if (clickedOutside) {
            event.target.close();
        }
    }
});
