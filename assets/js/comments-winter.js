; (function (factory) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', factory);
    } else {
        factory();
    }
}(function () {
    const wn = {
        ajax: $(this).request
    };

    class BlogPortalComments {

        /**
         * Create new BlogPortal Comments instance
         * @param {HTMLElement} parent Parent Container
         */
        constructor(parent) {
            this.parent = parent;
            this.parent.addEventListener('click', this.listener.bind(this));
            this.parent.addEventListener('submit', this.listener.bind(this));
        }

        /**
         * EventListener
         * @param {Event} event 
         */
        listener(event) {
            let target = event.target.closest('[data-blogportal-handler]');
            if (!target) {
                return;
            }

            // Skip Submit Event on Form
            if (target.tagName.toUpperCase() === 'FORM' && event.type !== 'submit') {
                return;
            }

            // Call Method
            let method = target.dataset.blogportalHandler;
            if (typeof this[method] !== 'undefined') {
                event.preventDefault();
                if (target.classList.contains('disabled') || target.disabled) {
                    return;
                } else {
                    this[method](target);
                }
            }
        }

        /**
         * Turn string into HTML element
         * @param {string} content 
         * @returns {HTMLElement}
         */
        stringToElement(content) {
            let temp = document.createElement('DIV');
            temp.innerHTML = content;
            return temp.children[0];
        }

        /**
         * Create a new Alert message box
         * @param {string} type 
         * @param {string|HTMLElement} content 
         * @returns {HTMLElement}
         */
        createAlert(type, content) {
            let alert = document.createElement('DIV');
            alert.className = `alert alert-${type}`;

            if (content instanceof HTMLElement) {
                alert.appendChild(content);
            } else {
                alert.innerHTML = content;
            }

            return alert;
        }

        /**
         * Show Loading Indicator
         * @param {NodeList} elements 
         */
        showLoading(elements) {
            Array.from(elements).map(el => {
                if (el.tagName.toUpperCase() === 'button') {
                    el.disabled = true;
                } else {
                    el.classList.add('disabled');
                }
                el.dataset.blogportalContent = el.innerHTML;
                el.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                el.blur();
            });
        }

        /**
         * Hide Loading Indicator
         * @param {NodeList} elements 
         */
        hideLoading(elements) {
            Array.from(elements).map(el => {
                if (el.tagName.toUpperCase() === 'button') {
                    el.disabled = false;
                } else {
                    el.classList.remove('disabled');
                }
                el.innerHTML = el.dataset.blogportalContent;
                delete el.dataset.blogportalContent;
            });
        }

        /**
         * Call Winter AJAX handler
         * @param {string} method The desired ajax method to call.
         * @param {object} data The data to send.
         * @param {object} config Additional configuration for the wn.ajax method,
         * @returns Promise
         */
        callWinter(method, data, config) {
            return new Promise((resolve, reject) => {
                wn.ajax(method, Object.assign({
                    data,
                    success: function (data, responseCode, xhr) {
                        resolve({ data, responseCode, xhr, wn: this });
                    },
                    error: function (data, responseCode, xhr) {
                        reject({ data, responseCode, xhr, wn: this });
                    }
                }, typeof config === 'object' ? config : {}));
            });
        }

        /**
         * Change Comment Status (Approve, Reject, Favorite)
         * @param {HTMLElement} el 
         */
        onChangeStatus(el) {
            if (!el.dataset.blogportalStatus || !el.dataset.blogportalId) {
                return false;
            }
            let parent = el.closest('[data-comment-id]');

            // Show Loading Indicator
            if (parent) {
                this.showLoading(parent.querySelectorAll('[data-blogportal-handler="onChangeStatus"]'));
            }

            // Call AJAX backend
            this.callWinter('onChangeStatus', {
                status: el.dataset.blogportalStatus,
                comment_id: el.dataset.blogportalId
            }).then(
                ({ data, responseCode, xhr, wn }) => {
                    if (parent) {
                        this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onChangeStatus"]'));

                        if (data.comment) {
                            parent.replaceWith(this.stringToElement(data.comment));
                        } else {
                            parent.remove();
                        }
                    }
                },
                ({ data, responseCode, xhr, wn }) => {
                    if (parent) {
                        this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onChangeStatus"]'));
                    }
                    alert(typeof data === 'object' ? data.result : data);
                }
            );
        }

        /**
         * Change Comment Status (Like, Dislike)
         * @param {HTMLElement} el 
         */
        onVote(el) {
            if (!el.dataset.blogportalVote || !el.dataset.blogportalId) {
                return false;
            }
            let parent = el.closest('[data-comment-id]');

            // Show Loading Indicator
            if (parent) {
                this.showLoading(parent.querySelectorAll('[data-blogportal-handler="onVote"]'));
            }

            // Call AJAX backend
            this.callWinter('onVote', {
                vote: el.dataset.blogportalVote,
                comment_id: el.dataset.blogportalId
            }).then(
                ({ data, responseCode, xhr, wn }) => {
                    if (parent) {
                        this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onVote"]'));

                        if (data.comment) {
                            parent.replaceWith(this.stringToElement(data.comment));
                        } else {
                            parent.remove();
                        }
                    }
                },
                ({ data, responseCode, xhr, wn }) => {
                    if (parent) {
                        this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onVote"]'));
                    }
                    alert(typeof data === 'object' ? data.result : data);
                }
            );
        }

        /**
         * Create Reply Form
         * @param {HTMLElement} el 
         */
        onCreateReply(el) {
            if (!el.dataset.blogportalId) {
                return false;
            }

            // Get Form
            let form = this.parent.querySelector('form');
            if (!form) {
                return false;
            }

            // Show Loading Indicator
            let parent = el.closest('[data-comment-id]');
            if (parent) {
                this.showLoading(parent.querySelectorAll('[data-blogportal-handler]'));
            }

            // Call AJAX backend
            this.callWinter('onCreateReply', {
                comment_id: el.dataset.blogportalId
            }).then(
                ({ data, responseCode, xhr }) => {
                    if (parent) {
                        this.hideLoading(parent.querySelectorAll('[data-blogportal-handler]'));
                    }
                    form.scrollIntoView({ behavior: "smooth" });
                    form.querySelector('button[type="submit"]').innerText = data.submitText;
                    form.querySelector('button[data-blogportal-handler="onCancelReply"]').style.removeProperty('display');

                    let hidden = document.createElement('INPUT');
                    hidden.type = 'hidden';
                    hidden.name = 'comment_parent';
                    hidden.value = data.comment.id;
                    form.appendChild(hidden);
                    form.insertBefore(this.stringToElement(data.reply), form.children[0]);
                },
                ({ data, responseCode, xhr }) => {
                    if (parent) {
                        this.hideLoading(parent.querySelectorAll('[data-blogportal-handler]'));
                    }
                    alert(typeof data === 'object' ? data.result : data);
                }
            );
        }

        /**
         * Cancel Reply Form
         * @param {HTMLElement} el 
         */
        onCancelReply(el) {
            let form = this.parent.querySelector('form');
            if (!form) {
                return false;
            }

            // Show Loading Indicator
            this.showLoading(form.querySelectorAll('button'));

            // Call AJAX backend
            this.callWinter('onCancelReply', {}).then(
                ({ data, responseCode, xhr }) => {
                    this.hideLoading(form.querySelectorAll('button'));
                    form.querySelector('button[type="submit"]').innerText = data.submitText;
                    form.querySelector('button[data-blogportal-handler="onCancelReply"]').style.display = 'none';

                    let replyTo = form.querySelector('.comment-form-reply-to');
                    if (replyTo) {
                        replyTo.remove();
                    }

                    let hidden = form.querySelector('input[name="comment_parent"]');
                    if (hidden) {
                        hidden.remove();
                    }
                },
                ({ data, responseCode, xhr }) => {
                    this.hideLoading(form.querySelectorAll('button'));
                    alert(typeof data === 'object' ? data.result : data);
                }
            );
        }

        /**
         * Reload GREGWAR Captcha
         * @param {HTMLElement} el 
         */
        onReloadCaptcha(el) {
            let form = this.parent.querySelector('form');
            if (!form) {
                return false;
            }

            // Show Loading Indicator
            this.showLoading(form.querySelectorAll('button'));

            // Call AJAX backend
            this.callWinter('onReloadCaptcha', {}).then(
                ({ data, responseCode, xhr }) => {
                    this.hideLoading(form.querySelectorAll('button'));

                    let image = form.querySelector('img.comment-form-captcha');
                    if (image) {
                        image.src = data.captchaImage;
                    }
                },
                ({ data, responseCode, xhr }) => {
                    this.hideLoading(form.querySelectorAll('button'));
                    alert(typeof data === 'object' ? data.result : data);
                }
            );
        }

        /**
         * Submit Comment or Reply
         * @param {HTMLElement} el 
         */
        onComment(el) {
            let form = this.parent.querySelector('form');
            if (!form) {
                return false;
            }

            // Show Loading Indicator
            this.showLoading(form.querySelectorAll('button'));

            // Call AJAX backend
            this.callWinter('onComment', Object.fromEntries([...(new FormData(form)).entries()])).then(
                ({ data, responseCode, xhr }) => {
                    this.hideLoading(form.querySelectorAll('button'));

                    let comments = this.stringToElement(data.comments);
                    this.parent.innerHTML = comments.innerHTML;
                },
                ({ data, responseCode, xhr }) => {
                    this.hideLoading(form.querySelectorAll('button'));

                    if (typeof data === 'object') {
                        let alert = this.createAlert('danger', data.message || data.X_WINTER_ERROR_MESSAGE);

                        let formAlert = form.querySelector('.alert');
                        if (formAlert) {
                            formAlert.replaceWith(alert);
                        } else {
                            form.insertBefore(alert, form.children[0]);
                        }

                        let image = form.querySelector('img.comment-form-captcha');
                        if (data.captchaImage && image) {
                            image.src = data.captchaImage;
                        }
                    } else {
                        alert(typeof data === 'object' ? data.result : data);
                    }

                }
            );
        }
    }
    Array.from(document.querySelectorAll('[data-blogportal-comments]')).map(el => {
        new BlogPortalComments(el);
    })

}));