import './preventViewportZoom';
import Cropper from 'cropperjs';

const MAX_BYTES = 256000;
const MAX_PICK_BYTES = 8 * 1024 * 1024;

function canvasToJpegBlob(canvas, quality) {
    return new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob), 'image/jpeg', quality);
    });
}

async function exportCroppedJpeg(cropper, maxBytes) {
    let canvas = cropper.getCroppedCanvas({
        width: 512,
        height: 512,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
    });

    let quality = 0.9;
    let blob = await canvasToJpegBlob(canvas, quality);

    while (blob && blob.size > maxBytes && quality > 0.45) {
        quality -= 0.07;
        blob = await canvasToJpegBlob(canvas, quality);
    }

    let scale = 0.9;
    while (blob && blob.size > maxBytes && scale > 0.5) {
        const smaller = document.createElement('canvas');
        smaller.width = Math.max(200, Math.floor(canvas.width * scale));
        smaller.height = Math.max(200, Math.floor(canvas.height * scale));
        const ctx = smaller.getContext('2d');
        ctx.drawImage(canvas, 0, 0, smaller.width, smaller.height);
        canvas = smaller;
        quality = 0.85;
        blob = await canvasToJpegBlob(canvas, quality);
        scale -= 0.1;
    }

    return blob;
}

function initStudentProfilePhotoCrop() {
    const root = document.getElementById('student-profile-photo-crop');
    if (!root) {
        return;
    }

    const picker = root.querySelector('[data-photo-picker]');
    const chooseBtn = root.querySelector('[data-photo-choose]');
    const fileInput = root.querySelector('[data-photo-file-input]');
    const form = root.querySelector('[data-photo-form]');
    const modal = root.querySelector('[data-crop-modal]');
    const cropImage = root.querySelector('[data-crop-image]');
    const saveBtn = root.querySelector('[data-crop-save]');
    const cancelBtn = root.querySelector('[data-crop-cancel]');
    const errorEl = root.querySelector('[data-crop-error]');
    const preview = root.querySelector('[data-photo-preview]');
    const initialsEl = root.querySelector('[data-photo-initials]');

    if (!picker || !fileInput || !form || !modal || !cropImage || !saveBtn || !cancelBtn) {
        return;
    }

    if (chooseBtn) {
        chooseBtn.addEventListener('click', () => picker.click());
    }

    let cropper = null;

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        cropImage.removeAttribute('src');
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        }
    };

    cancelBtn.addEventListener('click', () => {
        picker.value = '';
        closeModal();
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            picker.value = '';
            closeModal();
        }
    });

    picker.addEventListener('change', () => {
        const file = picker.files?.[0];
        if (!file) {
            return;
        }

        if (file.size > MAX_PICK_BYTES) {
            if (errorEl) {
                errorEl.textContent = root.dataset.errorTooLarge || 'Image is too large. Choose a smaller file.';
                errorEl.classList.remove('hidden');
            }
            picker.value = '';

            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            cropImage.src = reader.result;
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');

            if (cropper) {
                cropper.destroy();
            }

            cropper = new Cropper(cropImage, {
                aspectRatio: 1,
                viewMode: 2,
                dragMode: 'move',
                autoCropArea: 0.85,
                responsive: true,
                guides: true,
                background: false,
                movable: true,
                zoomable: true,
                scalable: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
            });
        };
        reader.readAsDataURL(file);
    });

    saveBtn.addEventListener('click', async () => {
        if (!cropper) {
            return;
        }

        saveBtn.disabled = true;

        try {
            const blob = await exportCroppedJpeg(cropper, MAX_BYTES);
            if (!blob || blob.size > MAX_BYTES) {
                if (errorEl) {
                    errorEl.textContent = root.dataset.errorMaxSize || 'Cropped image must be 250 KB or less.';
                    errorEl.classList.remove('hidden');
                }

                return;
            }

            const croppedFile = new File([blob], 'quizsnap-student-profile-photo.jpg', { type: 'image/jpeg' });
            const transfer = new DataTransfer();
            transfer.items.add(croppedFile);
            fileInput.files = transfer.files;

            if (preview) {
                preview.src = URL.createObjectURL(blob);
                preview.classList.remove('hidden');
            }
            if (initialsEl) {
                initialsEl.classList.add('hidden');
            }

            closeModal();
            picker.value = '';
            form.requestSubmit();
        } finally {
            saveBtn.disabled = false;
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStudentProfilePhotoCrop);
} else {
    initStudentProfilePhotoCrop();
}
