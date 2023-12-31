class BlogPortalComments {
       constructor(parent) {
              // Constructor initializes the BlogPortalComments instance and adds event listeners to the parent element.
              if (!parent) {
                     return; // If 'parent' is not provided, do nothing and return.
              }
              this.parent = parent;
              // Add event listeners for 'click' and 'submit' events to 'clickHandler'.
              this.parent.addEventListener('click', this.clickHandler.bind(this));
              this.parent.addEventListener('submit', this.clickHandler.bind(this));
       }

       // Method to handle 'click' and 'submit' events for specific elements.
       clickHandler = async (event) => {
              // Find the target with 'data-blogportal-handler' attribute.
              const target = event.target.closest('[data-blogportal-handler]');
              if (!target) {
                     return; // If no target is found, exit.
              }

              // For forms and non-submit events, exit the handler.
              if (target.tagName.toUpperCase() === 'FORM' && event.type !== 'submit') {
                     return;
              }

              // Get the method to execute based on the 'data-blogportal-handler'.
              const method = target.dataset.blogportalHandler;
              if (this[method]) {
                     event.preventDefault();
                     // If the target is disabled, don't proceed further; else, execute the method.
                     if (target.classList.contains('disabled') || target.disabled) {
                            return;
                     } else {
                            try {
                                   // Execute the defined method with the target as a parameter.
                                   await this[method](target);
                            } catch (error) {
                                   // Handle errors using the 'handleError' method.
                                   this.handleError(error, [target]);
                            }
                     }
              }
       };

       // Method to convert HTML string to an HTML element.
       stringToElement(content) {
              const temp = document.createElement('div');
              temp.innerHTML = content;
              return temp.firstElementChild;
       }

       // Method to create an alert element with a specific type and content.
       createAlert(type, content) {
              const alert = document.createElement('div');
              alert.className = `alert alert-${type}`;

              // Check if the content is an HTML element and append it to the alert, else add it as inner HTML.
              if (content instanceof HTMLElement) {
                     alert.appendChild(content);
              } else {
                     alert.innerHTML = content;
              }

              return alert;
       }

       // Method to show loading state on elements.
       showLoading(elements) {
              elements.forEach(el => {
                     if (el.tagName.toUpperCase() === 'BUTTON') {
                            el.disabled = true;
                     } else {
                            el.classList.add('disabled');
                     }
                     el.classList.add('wn-loading');
              });
       }

       // Method to hide loading state on elements.
       hideLoading(elements) {
              elements.forEach(el => {
                     if (el.tagName.toUpperCase() === 'BUTTON') {
                            el.disabled = false;
                     } else {
                            el.classList.remove('disabled');
                     }
                     el.classList.remove('wn-loading');
              });
       }

       // Method to handle errors uniformly and display appropriate alerts.
       handleError = (error, elements) => {
              this.hideLoading(elements);
              const errorMessage = error.response ? error.response.message : error;
              const alertType = error.response ? 'danger' : 'warning';
              const errorAlert = this.createAlert(alertType, errorMessage);
              const form = this.parent.querySelector('form');
              const myAlert = form.querySelector('.alert');
              if (myAlert) {
                     myAlert.replaceWith(errorAlert);
              } else {
                     form.insertBefore(errorAlert, form.children[0]);
              }
              const image = form.querySelector('img.comment-form-captcha');
              if (error.response && error.response.captchaImage && image) {
                     image.src = error.response.captchaImage;
              }
              setTimeout(() => {
                     errorAlert.remove();
              }, 5000);
       };

       // Method to make asynchronous Winter calls with specified method and data.
       async callWinter(method, data) {
              return new Promise((resolve, reject) => {
                     Snowboard.request(this.parent, method, {
                            data,
                            success: (data) => {
                                   resolve({ data, snowboard: this });
                            },
                            error: (response) => {
                                   reject({ response, snowboard: this });
                            },
                     });
              });
       }

       // Method to handle changing the status of a comment.
       onChangeStatus = async (el) => {
              if (!el.dataset.blogportalStatus || !el.dataset.blogportalId) {
                     return false;
              }
              const parent = el.closest('[data-comment-id]');

              if (parent) {
                     this.showLoading(parent.querySelectorAll('[data-blogportal-handler="onChangeStatus"]'));
              }

              try {
                     const { data } = await this.callWinter('onChangeStatus', {
                            status: el.dataset.blogportalStatus,
                            comment_id: el.dataset.blogportalId,
                     });

                     if (parent) {
                            this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onChangeStatus"]'));

                            if (data.comment) {
                                   parent.replaceWith(this.stringToElement(data.comment));
                            } else {
                                   parent.remove();
                            }
                     }
              } catch (error) {
                     if (parent) {
                            this.handleError(error, parent.querySelectorAll('[data-blogportal-handler="onChangeStatus"]'));
                     }
              }
       }

       // Revised onVote method for improved error handling
       onVote = async (el) => {
              if (!el.dataset.blogportalVote || !el.dataset.blogportalId) {
                     return false;
              }
              const parent = el.closest('[data-comment-id]');

              if (parent) {
                     this.showLoading(parent.querySelectorAll('[data-blogportal-handler="onVote"]'));
              }

              try {
                     const { data } = await this.callWinter('onVote', {
                            vote: el.dataset.blogportalVote,
                            comment_id: el.dataset.blogportalId,
                     });

                     if (parent) {
                            this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onVote"]'));

                            if (data.comment) {
                                   parent.replaceWith(this.stringToElement(data.comment));
                            } else {
                                   parent.remove();
                            }
                     }
              } catch (error) {
                     if (parent) {
                            this.handleError(error, parent.querySelectorAll('[data-blogportal-handler="onVote"]'));
                     }
              }
       };

       // Method to create a reply to a comment.
       onCreateReply = async (el) => {
              if (!el.dataset.blogportalId) {
                     return false;
              }

              const form = this.parent.querySelector('form');
              if (!form) {
                     return false;
              }

              const parent = el.closest('[data-comment-id]');
              if (parent) {
                     this.showLoading(parent.querySelectorAll('[data-blogportal-handler]'));
              }

              const existingReply = form.querySelector('.reply');

              try {
                     const { data } = await this.callWinter('onCreateReply', {
                            comment_id: el.dataset.blogportalId,
                     });

                     if (parent) {
                            this.hideLoading(parent.querySelectorAll('[data-blogportal-handler]'));
                     }
                     form.scrollIntoView({ behavior: 'smooth' });
                     form.querySelector('button[type="submit"]').innerText = data.submitText;
                     form.querySelector('button[data-blogportal-handler="onCancelReply"]').classList.remove('hidden');

                     const hidden = document.createElement('input');
                     hidden.type = 'hidden';
                     hidden.name = 'comment_parent';
                     hidden.value = data.comment.id;
                     form.appendChild(hidden);

                     const replyElement = this.stringToElement(data.reply);
                     replyElement.classList.add('reply');

                     if (existingReply) {
                            form.replaceChild(replyElement, existingReply);
                     } else {
                            form.insertBefore(replyElement, form.children[0]);
                     }
              } catch (error) {
                     if (parent) {
                            this.handleError(error, parent.querySelectorAll('[data-blogportal-handler]'));
                     }
              }
       }

       // Method to cancel a reply to a comment.
       onCancelReply = async () => {
              const form = this.parent.querySelector('form');
              if (!form) {
                     return false;
              }

              this.showLoading(form.querySelectorAll('button'));

              try {
                     const { data } = await this.callWinter('onCancelReply', {});

                     this.hideLoading(form.querySelectorAll('button'));
                     form.querySelector('button[type="submit"]').innerText = data.submitText;
                     form.querySelector('button[data-blogportal-handler="onCancelReply"]').classList.add('hidden');

                     const replyTo = form.querySelector('.comment-form-reply-to');
                     if (replyTo) {
                            replyTo.remove();
                     }

                     const hidden = form.querySelector('input[name="comment_parent"]');
                     if (hidden) {
                            hidden.remove();
                     }
              } catch (error) {
                     this.handleError(error, form.querySelectorAll('button'));
              }
       }

       // Method to reload captcha image in the comment form.
       onReloadCaptcha = async () => {
              const form = this.parent.querySelector('form');
              if (!form) {
                     return false;
              }

              this.showLoading(form.querySelectorAll('button'));

              try {
                     const { data } = await this.callWinter('onReloadCaptcha', {});

                     this.hideLoading(form.querySelectorAll('button'));

                     const image = form.querySelector('img.comment-form-captcha');
                     if (image) {
                            image.src = data.captchaImage;
                     }
              } catch (error) {
                     this.handleError(error, form.querySelectorAll('button'));
              }
       }

       // Method to submit a comment.
       onComment = async (el) => {
              let form = this.parent.querySelector('form');
              if (!form) {
                     return false;
              }

              if (!form.checkValidity()) {
                     const validationErrorAlert = this.createAlert('danger', 'Please fill out the form correctly before submitting.');
                     const formAlert = form.querySelector('.alert');
                     if (formAlert) {
                            formAlert.replaceWith(validationErrorAlert);
                     } else {
                            form.insertBefore(validationErrorAlert, form.children[0]);
                     }
                     setTimeout(() => {
                            validationErrorAlert.remove();
                     }, 5000);
                     form.classList.add('was-validated');
                     return;
              }

              const formAlert = form.querySelector('.alert');
              if (formAlert) {
                     formAlert.remove();
              }

              // Show Loading Indicator
              this.showLoading(form.querySelectorAll('button'));

              try {
                     const { data } = await this.callWinter('onComment', Object.fromEntries([...new FormData(form).entries()]));

                     this.hideLoading(form.querySelectorAll('button'));

                     if (data) {
                            const comments = this.stringToElement(data.comments);
                            this.parent.innerHTML = comments.innerHTML;
                            const form = this.parent.querySelector('form');
                            const successMessage = data.message;
                            const successAlert = this.createAlert('success', successMessage);
                            const myAlert = form.querySelector('.alert');
                            if (myAlert) {
                                   myAlert.replaceWith(successAlert);
                            } else {
                                   form.insertBefore(successAlert, form.children[0]);
                            }
                            form.reset();
                            form.classList.remove('was-validated');
                            setTimeout(() => {
                                   successAlert.remove();
                            }, 5000);
                     }
              } catch (error) {
                     if (error.response) {
                            this.handleError(error, form.querySelectorAll('button'));
                     }
              }
       }
}

// Function to initialize BlogPortalComments on document load or immediately if the document is already loaded.
function onDOMContentLoaded() {
       document.querySelectorAll('[data-blogportal-comments]').forEach((el) => {
              new BlogPortalComments(el);
       });
}

if (document.readyState === 'loading') {
       document.addEventListener('DOMContentLoaded', onDOMContentLoaded);
} else {
       onDOMContentLoaded();
}
