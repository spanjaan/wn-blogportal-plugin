/* ============================================================
   BlogPortal Comments
   blogportal-comments.js
   ============================================================ */

/* ---- Alerts ----------------------------------------------- */

const ALERT_ICONS = {
    success: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`,
    danger:  `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    warning: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
};

function createAlert(type, content) {
    const safeType = ALERT_ICONS[type] ? type : 'warning';

    const alert = document.createElement('div');
    alert.className = `blogportal-alert blogportal-alert--${safeType}`;

    const iconWrap = document.createElement('span');
    iconWrap.className = 'blogportal-alert__icon';
    iconWrap.innerHTML = ALERT_ICONS[safeType];

    const text = document.createElement('span');
    content instanceof HTMLElement ? text.appendChild(content) : (text.textContent = content);

    alert.appendChild(iconWrap);
    alert.appendChild(text);

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            alert.style.opacity = '1';
            alert.style.transform = 'translateY(0)';
        });
    });

    return alert;
}

function dismissAlert(alertEl, delay = 5000) {
    setTimeout(() => {
        alertEl.style.opacity = '0';
        alertEl.style.transform = 'translateY(-8px)';
        setTimeout(() => alertEl.remove(), 250);
    }, delay);
}

/* ---- Field Errors ----------------------------------------- */

const FIELD_ERROR_ICON = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;

function applyFieldError(field, message) {
    field.classList.add('blogportal-field--error');

    let errorEl = field.parentElement.querySelector('.blogportal-field-error');
    if (!errorEl) {
        errorEl = document.createElement('p');
        errorEl.className = 'blogportal-field-error';
        field.parentElement.appendChild(errorEl);
    }

    errorEl.innerHTML = `${FIELD_ERROR_ICON} ${message}`;

    const clearError = () => {
        field.classList.remove('blogportal-field--error');
        errorEl.remove();
        field.removeEventListener('input',  clearError);
        field.removeEventListener('change', clearError);
    };

    field.addEventListener('input',  clearError);
    field.addEventListener('change', clearError);
}

function clearFieldErrors(form) {
    form.querySelectorAll('.blogportal-field-error').forEach(el => el.remove());
    form.querySelectorAll('input, textarea, select').forEach(field => {
        field.classList.remove('blogportal-field--error');
    });
}

/* ---- Form Validation -------------------------------------- */

function validateForm(form) {
    let isValid = true;

    clearFieldErrors(form);

    form.querySelectorAll('input[required], textarea[required], select[required]').forEach(field => {
        if (field.type === 'hidden' || field.tabIndex === -1) return;

        const value = field.value.trim();
        let errorMessage = null;

        if (field.type === 'checkbox') {
            if (!field.checked) {
                errorMessage = field.dataset.errorRequired || 'This field is required.';
            }
        } else if (!value) {
            errorMessage = field.dataset.errorRequired || 'This field is required.';
        } else if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            errorMessage = field.dataset.errorEmail || 'Please enter a valid email address.';
        } else if (field.minLength > 0 && value.length < field.minLength) {
            errorMessage = field.dataset.errorMinlength || `Minimum ${field.minLength} characters required.`;
        } else if (field.maxLength > 0 && value.length > field.maxLength) {
            errorMessage = field.dataset.errorMaxlength || `Maximum ${field.maxLength} characters allowed.`;
        } else if (field.pattern && !new RegExp(`^${field.pattern}$`).test(value)) {
            errorMessage = field.dataset.errorPattern || 'Please match the requested format.';
        }

        if (errorMessage) {
            applyFieldError(field, errorMessage);
            isValid = false;
        }
    });

    return isValid;
}

/* ---- Loading State ---------------------------------------- */

function showLoading(elements) {
    elements.forEach(el => {
        el.tagName.toUpperCase() === 'BUTTON' ? (el.disabled = true) : el.classList.add('disabled');
        el.classList.add('wn-loading');
    });
}

function hideLoading(elements) {
    elements.forEach(el => {
        el.tagName.toUpperCase() === 'BUTTON' ? (el.disabled = false) : el.classList.remove('disabled');
        el.classList.remove('wn-loading');
    });
}

/* ---- Main Class ------------------------------------------- */

class BlogPortalComments {
    constructor(parent) {
        if (!parent) return;

        this.parent = parent;
        this.parent.addEventListener('click',  this.clickHandler.bind(this));
        this.parent.addEventListener('submit', this.clickHandler.bind(this));
    }

    clickHandler = async (event) => {
        const target = event.target.closest('[data-blogportal-handler]');
        if (!target) return;

        if (target.tagName.toUpperCase() === 'FORM' && event.type !== 'submit') return;

        const method = target.dataset.blogportalHandler;
        if (!this[method]) return;

        event.preventDefault();
        if (target.classList.contains('disabled') || target.disabled) return;

        try {
            await this[method](target);
        } catch (error) {
            this.handleError(error, [target]);
        }
    };

    stringToElement(content) {
        const temp = document.createElement('div');
        temp.innerHTML = content;
        return temp.firstElementChild;
    }

    showFormAlert(form, type, message, autoDismiss = 5000) {
        const alertEl = createAlert(type, message);
        const existing = form.querySelector('.blogportal-alert');
        existing ? existing.replaceWith(alertEl) : form.insertBefore(alertEl, form.firstElementChild);
        if (autoDismiss > 0) dismissAlert(alertEl, autoDismiss);
        return alertEl;
    }

    handleError = (error, elements) => {
        hideLoading(elements);

        const message   = error.response ? error.response.message : error;
        const alertType = error.response ? 'danger' : 'warning';
        const form      = this.parent.querySelector('form');

        if (form) {
            this.showFormAlert(form, alertType, message);
            const captchaImg = form.querySelector('img.comment-form-captcha');
            if (error.response?.captchaImage && captchaImg) {
                captchaImg.src = error.response.captchaImage;
            }
        }
    };

    async callWinter(method, data) {
        return new Promise((resolve, reject) => {
            Snowboard.request(this.parent, method, {
                data,
                success: (data)     => resolve({ data, snowboard: this }),
                error:   (response) => reject({ response, snowboard: this }),
            });
        });
    }

    onChangeStatus = async (el) => {
        if (!el.dataset.blogportalStatus || !el.dataset.blogportalId) return;

        const commentEl   = el.closest('[data-comment-id]');
        const loadTargets = commentEl?.querySelectorAll('[data-blogportal-handler="onChangeStatus"]');

        if (loadTargets) showLoading(loadTargets);

        try {
            const { data } = await this.callWinter('onChangeStatus', {
                status:     el.dataset.blogportalStatus,
                comment_id: el.dataset.blogportalId,
            });

            if (commentEl) {
                hideLoading(loadTargets);
                data.comment ? commentEl.replaceWith(this.stringToElement(data.comment)) : commentEl.remove();
            }
        } catch (error) {
            if (commentEl) this.handleError(error, loadTargets);
        }
    };

    onVote = async (el) => {
        if (!el.dataset.blogportalVote || !el.dataset.blogportalId) return;

        const commentEl   = el.closest('[data-comment-id]');
        const loadTargets = commentEl?.querySelectorAll('[data-blogportal-handler="onVote"]');

        if (loadTargets) showLoading(loadTargets);

        try {
            const { data } = await this.callWinter('onVote', {
                vote:       el.dataset.blogportalVote,
                comment_id: el.dataset.blogportalId,
            });

            if (commentEl) {
                hideLoading(loadTargets);
                data.comment ? commentEl.replaceWith(this.stringToElement(data.comment)) : commentEl.remove();
            }
        } catch (error) {
            if (commentEl) this.handleError(error, loadTargets);
        }
    };

    onCreateReply = async (el) => {
        if (!el.dataset.blogportalId) return;

        const form = this.parent.querySelector('form');
        if (!form) return;

        const commentEl   = el.closest('[data-comment-id]');
        const loadTargets = commentEl?.querySelectorAll('[data-blogportal-handler]');

        if (loadTargets) showLoading(loadTargets);

        const existingReply = form.querySelector('.reply');

        try {
            const { data } = await this.callWinter('onCreateReply', {
                comment_id: el.dataset.blogportalId,
            });

            if (loadTargets) hideLoading(loadTargets);

            form.scrollIntoView({ behavior: 'smooth' });

            const sendIcon = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`;
            form.querySelector('button[type="submit"]').innerHTML = `${sendIcon} ${data.submitText}`;

            form.querySelector('button[data-blogportal-handler="onCancelReply"]').classList.remove('hidden');

            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name  = 'comment_parent';
            hidden.value = data.comment.id;
            form.appendChild(hidden);

            const replyElement = this.stringToElement(data.reply);
            replyElement.classList.add('reply');
            existingReply
                ? form.replaceChild(replyElement, existingReply)
                : form.insertBefore(replyElement, form.firstElementChild);

        } catch (error) {
            if (commentEl) this.handleError(error, loadTargets);
        }
    };

    onCancelReply = async () => {
        const form = this.parent.querySelector('form');
        if (!form) return;

        const buttons = form.querySelectorAll('button');
        showLoading(buttons);

        try {
            const { data } = await this.callWinter('onCancelReply', {});

            hideLoading(buttons);
            const sendIcon = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`;
            form.querySelector('button[type="submit"]').innerHTML = `${sendIcon} ${data.submitText}`;
            form.querySelector('button[data-blogportal-handler="onCancelReply"]').classList.add('hidden');
            form.querySelector('.comment-form-reply-to')?.remove();
            form.querySelector('input[name="comment_parent"]')?.remove();

        } catch (error) {
            this.handleError(error, buttons);
        }
    };

    onReloadCaptcha = async () => {
        const form = this.parent.querySelector('form');
        if (!form) return;

        const buttons = form.querySelectorAll('button');
        showLoading(buttons);

        try {
            const { data } = await this.callWinter('onReloadCaptcha', {});

            hideLoading(buttons);
            const image = form.querySelector('img.comment-form-captcha');
            if (image) image.src = data.captchaImage;

        } catch (error) {
            this.handleError(error, buttons);
        }
    };

    onComment = async () => {
        const form = this.parent.querySelector('form');
        if (!form) return;

        if (!validateForm(form)) return;

        form.querySelector('.blogportal-alert')?.remove();

        const buttons = form.querySelectorAll('button');
        showLoading(buttons);

        try {
            const { data } = await this.callWinter(
                'onComment',
                Object.fromEntries([...new FormData(form).entries()])
            );

            hideLoading(buttons);

            if (data) {
                const comments = this.stringToElement(data.comments);
                this.parent.innerHTML = comments.innerHTML;

                const freshForm = this.parent.querySelector('form');
                this.showFormAlert(freshForm, 'success', data.message);
                freshForm.reset();
            }

        } catch (error) {
            if (error.response) this.handleError(error, form.querySelectorAll('button'));
        }
    };
}

/* ---- Bootstrap -------------------------------------------- */

function onDOMContentLoaded() {
    document.querySelectorAll('[data-blogportal-comments]').forEach(el => {
        new BlogPortalComments(el);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onDOMContentLoaded);
} else {
    onDOMContentLoaded();
}