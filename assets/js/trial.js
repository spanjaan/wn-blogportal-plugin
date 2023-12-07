class BlogPortalComments {
 constructor(parent) {
  if (!parent) {
   return;
  }
  this.parent = parent;
  this.parent.addEventListener('click', this.clickHandler.bind(this));
  this.parent.addEventListener('submit', this.clickHandler.bind(this));
 }

 clickHandler = async (event) => {
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
    try {
     await this[method](target);
    } catch (error) {
     alert(typeof error === 'object' ? error.result : error);
    }
   }
  }
 }

 stringToElement(content) {
  const temp = document.createElement('div');
  temp.innerHTML = content;
  return temp.firstElementChild;
 }

 createAlert(type, content) {
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
    this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onChangeStatus"]'));
   }
   alert(typeof error === 'object' ? error.result : error);
  }
 }

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
    this.hideLoading(parent.querySelectorAll('[data-blogportal-handler="onVote"]'));
   }
   alert(typeof error === 'object' ? error.result : error);
  }
 }

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
  } catch (error) {
   if (parent) {
    this.hideLoading(parent.querySelectorAll('[data-blogportal-handler]'));
   }
   alert(typeof error === 'object' ? error.result : error);
  }
 }

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
   form.querySelector('button[data-blogportal-handler="onCancelReply"]').style.display = 'none';

   const replyTo = form.querySelector('.comment-form-reply-to');
   if (replyTo) {
    replyTo.remove();
   }

   const hidden = form.querySelector('input[name="comment_parent"]');
   if (hidden) {
    hidden.remove();
   }
  } catch (error) {
   this.hideLoading(form.querySelectorAll('button'));
   alert(typeof error === 'object' ? error.result : error);
  }
 }

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
   this.hideLoading(form.querySelectorAll('button'));
   alert(typeof error === 'object' ? error.result : error);
  }
 }

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
   this.hideLoading(form.querySelectorAll('button'));

   if (error.response) {
    const form = this.parent.querySelector('form');
    const errorMessage = error.response.message;
    const errorAlert = this.createAlert('danger', errorMessage);
    const myAlert = form.querySelector('.alert');
    if (myAlert) {
     myAlert.replaceWith(errorAlert);
    } else {
     form.insertBefore(errorAlert, form.children[0]);
    }
    const image = form.querySelector('img.comment-form-captcha');
    if (error.response.captchaImage && image) {
     image.src = error.response.captchaImage;
    }
    setTimeout(() => {
     errorAlert.remove();
    }, 5000);
   }
  }
 }
}

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
