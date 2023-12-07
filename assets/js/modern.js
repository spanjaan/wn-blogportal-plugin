class BlogPortalComments {
  constructor(parent) {
    // Constructor for the BlogPortalComments class
    if (!parent) {
      return;
    }
    this.parent = parent;
    this.parent.addEventListener('click', this.clickHandler.bind(this));
    this.parent.addEventListener('submit', this.clickHandler.bind(this));
  }

  clickHandler(event) {
    // Event handler for click events
    const target = event.target.closest('[data-blogportal-handler]');
    if (!target) {
      return;
    }

    if (target.tagName.toUpperCase() === 'FORM' && event.type !== 'submit') {
      return;
    }

    const method = target.dataset.blogportalHandler;
    if (this[method]) {
      event.preventDefault();
      if (target.classList.contains('disabled') || target.disabled) {
        return;
      } else {
        this[method](target);
      }
    }
  }

  stringToElement(content) {
    // Converts a string into an HTML element
    const temp = document.createElement('div');
    temp.innerHTML = content;
    return temp.firstElementChild;
  }

  createAlert(type, content) {
    // Creates an alert message element
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;

    if (content instanceof HTMLElement) {
      alert.appendChild(content);
    } else {
      alert.innerHTML = content;
    }

    return alert;
  }

  showLoading(elements) {
    // Shows loading indicators on elements
    elements.forEach(el => {
      if (el.tagName.toUpperCase() === 'BUTTON') {
        el.disabled = true;
      } else {
        el.classList.add('disabled');
      }
      el.classList.add('wn-loading');
    });
  }

  hideLoading(elements) {
    // Hides loading indicators from elements
    elements.forEach(el => {
      if (el.tagName.toUpperCase() === 'BUTTON') {
        el.disabled = false;
      } else {
        el.classList.remove('disabled');
      }
      el.classList.remove('wn-loading');
    });
  }

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

  onChangeStatus(el) {
    // Handles a change in comment status (e.g., Approve, Reject, Favorite)
    if (!el.dataset.blogportalStatus || !el.dataset.blogportalId) {
      return false;
    }
    const parent = el.closest('[data-comment-id]');

    if (parent) {
      this.showLoading(parent.querySelectorAll('[data-blogportal-handler="onChangeStatus"]'));
    }

    this.callWinter('onChangeStatus', {
      status: el.dataset.blogportalStatus,
      comment_id: el.dataset.blogportalId,
    })
      .then(
        ({ data }) => {
          if (parent) {
            this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onChangeStatus"]'));

            if (data.comment) {
              parent.replaceWith(this.stringToElement(data.comment));
            } else {
              parent.remove();
            }
          }
        },
        ({ data }) => {
          if (parent) {
            this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onChangeStatus"]'));
          }
          alert(typeof data === 'object' ? data.result : data);
        }
      );
  }

  onVote(el) {
    // Handles voting on a comment (e.g., Like, Dislike)
    if (!el.dataset.blogportalVote || !el.dataset.blogportalId) {
      return false;
    }
    const parent = el.closest('[data-comment-id]');

    if (parent) {
      this.showLoading(parent.querySelectorAll('[data-blogportal-handler="onVote"]'));
    }

    this.callWinter('onVote', {
      vote: el.dataset.blogportalVote,
      comment_id: el.dataset.blogportalId,
    })
      .then(
        ({ data }) => {
          if (parent) {
            this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onVote"]'));

            if (data.comment) {
              parent.replaceWith(this.stringToElement(data.comment));
            } else {
              parent.remove();
            }
          }
        },
        ({ data }) => {
          if (parent) {
            this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onVote"]'));
          }
          alert(typeof data === 'object' ? data.result : data);
        }
      );
  }

  onCreateReply(el) {
    // Handles the creation of a reply form
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

    this.callWinter('onCreateReply', {
      comment_id: el.dataset.blogportalId,
    })
      .then(
        ({ data }) => {
          if (parent) {
            this.hideLoading(parent.querySelectorAll('[data-blogportal-handler]'));
          }
          form.scrollIntoView({ behavior: 'smooth' });
          form.querySelector('button[type="submit"]').innerText = data.submitText;
          form.querySelector('button[data-blogportal-handler="onCancelReply"]').style.removeProperty('display');

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
        },
        ({ data }) => {
          if (parent) {
            this.hideLoading(parent.querySelectorAll('[data-blogportal-handler]'));
          }
          alert(typeof data === 'object' ? data.result : data);
        }
      );
  }

  onCancelReply() {
    // Handles the cancellation of a reply form
    const form = this.parent.querySelector('form');
    if (!form) {
      return false;
    }

    this.showLoading(form.querySelectorAll('button'));

    this.callWinter('onCancelReply', {})
      .then(
        ({ data }) => {
          this.hideLoading(form.querySelectorAll('button'));
          form.querySelector('button[type="submit"]').innerText = data.submitText;
          form.querySelector('button[data-blogportal-handler="onCancelReply"]').style.display = 'none';

          const replyTo = form.querySelector('.comment-form-reply-to');
          if (replyTo) {
            replyTo.remove();
          }

          const hidden = form.querySelector('input[name="comment_parent"]');
          if (hidden) {
            hidden.remove();
          }
        },
        ({ data }) => {
          this.hideLoading(form.querySelectorAll('button'));
          alert(typeof data === 'object' ? data.result : data);
        }
      );
  }

  onReloadCaptcha() {
    // Handles the reloading of GREGWAR Captcha
    const form = this.parent.querySelector('form');
    if (!form) {
      return false;
    }

    this.showLoading(form.querySelectorAll('button'));

    this.callWinter('onReloadCaptcha', {})
      .then(
        ({ data }) => {
          this.hideLoading(form.querySelectorAll('button'));

          const image = form.querySelector('img.comment-form-captcha');
          if (image) {
            image.src = data.captchaImage;
          }
        },
        ({ data }) => {
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
    // Use Bootstrap's native validation methods
    if (!form.checkValidity()) {
      // Create a validation error alert
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

    // Call AJAX backend
    this.callWinter('onComment', Object.fromEntries([...new FormData(form).entries()]))
      .then(({ data }) => {
        this.hideLoading(form.querySelectorAll('button'));
        if (data) {
          const comments = this.stringToElement(data.comments);
          this.parent.innerHTML = comments.innerHTML;
          const successMessage = data.message;
          // Create a success alert
          const successAlert = this.createAlert('success', successMessage);
          // Append the success alert to the form or any other desired location
          const form = this.parent.querySelector('form');
          const myAlert = form.querySelector('.alert');
          if (myAlert) {
            myAlert.replaceWith(successAlert);
          } else {
            form.insertBefore(successAlert, form.children[0]);
          }
          form.reset();
          form.classList.remove('was-validated');
          // Hide the success message after 5 seconds
          setTimeout(() => {
            successAlert.remove();
          }, 5000);
        }
      })
      .catch(({ response }) => {
        this.hideLoading(form.querySelectorAll('button'));

        if (response) {
          const errorMessage = response.message;
          // Create an error alert
          const errorAlert = this.createAlert('danger', errorMessage);
          // Append the error alert to the form or any other desired location
          const form = this.parent.querySelector('form');
          const myAlert = form.querySelector('.alert');
          if (myAlert) {
            myAlert.replaceWith(errorAlert);
          } else {
            form.insertBefore(errorAlert, form.children[0]);
          }
          const image = form.querySelector('img.comment-form-captcha');
          if (response.captchaImage && image) {
            image.src = response.captchaImage;
          }
          // Hide the error message after 5 seconds
          setTimeout(() => {
            errorAlert.remove();
          }, 5000);
        }
      });
  }
}

function onDOMContentLoaded() {
  // Function to initialize BlogPortalComments instances when the DOM is ready
  document.querySelectorAll('[data-blogportal-comments]').forEach((el) => {
    new BlogPortalComments(el);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', onDOMContentLoaded);
} else {
  onDOMContentLoaded();
}
