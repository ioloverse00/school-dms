// Application-only Spark interactions; preserve all successful form controls.
(() => {
 const notificationPanel = document.getElementById('portal-notification-panel');
 let notificationRequest;
 notificationPanel?.addEventListener('click', async event => {
  const filter = event.target.closest('[data-notification-filter]');
  if (!filter || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
  event.preventDefault();
  notificationRequest?.abort();
  const request = new AbortController(); notificationRequest = request;
  notificationPanel.setAttribute('aria-busy', 'true');
  try {
   const url = new URL(notificationPanel.dataset.endpoint, location.origin);
   url.searchParams.set('notification_filter', filter.dataset.notificationFilter);
   const response = await fetch(url, {headers:{Accept:'application/json'},signal:request.signal});
   if (!response.ok) throw new Error('Notification filter unavailable.');
   const payload = await response.json();
   if (typeof payload.html !== 'string') throw new Error('Notification filter unavailable.');
   notificationPanel.innerHTML = payload.html;
   const notificationList = notificationPanel.querySelector('.portal-notification-list');
   if (notificationList?.animate && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    notificationList.animate([{opacity:0,transform:'translateY(3px)'},{opacity:1,transform:'translateY(0)'}], {duration:150,easing:'ease-out'});
   }
   notificationPanel.querySelector('[aria-current="page"]')?.focus();
  } catch (error) {
   if (error.name !== 'AbortError') window.dispatchEvent(new CustomEvent('portal:feedback', {detail:{tone:'danger',message:'Could not load notifications. Please try again.'}}));
  } finally {
   if (notificationRequest === request) notificationPanel.removeAttribute('aria-busy');
  }
 });
 const clock = document.getElementById('portal-navbar-clock');
 if (clock) {
  const formatter = new Intl.DateTimeFormat('en-US', {timeZone:'Asia/Manila',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true,month:'2-digit',day:'2-digit',year:'numeric'});
  const updateClock = () => {
   const now = new Date();
   const parts = Object.fromEntries(formatter.formatToParts(now).map(part => [part.type, part.value]));
   clock.textContent = `${parts.hour}:${parts.minute}:${parts.second} ${parts.dayPeriod} | ${parts.month}-${parts.day}-${parts.year}`;
   clock.dateTime = now.toISOString();
  };
  updateClock();
  setInterval(() => { if (!document.hidden) updateClock(); }, 1000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) updateClock(); });
 }
 // Native browser tooltips work without the template CSS or tooltip plugins.
 const titleSelector = 'button, input[type="submit"], input[type="button"], a.btn, a[role="button"]';
 const refreshActionTitles = () => document.querySelectorAll(titleSelector).forEach(control => {
  if (control.hasAttribute('title') && !control.hasAttribute('data-portal-auto-title')) return;
  const label = control.getAttribute('aria-label') || control.getAttribute('data-confirm') || (control.matches('input') ? control.value : control.textContent);
  const title = label?.replace(/\s+/g, ' ').trim();
  if (!title || control.getAttribute('title') === title) return;
  control.setAttribute('title', title);
  control.setAttribute('data-portal-auto-title', '');
 });
 refreshActionTitles();
 let titlesQueued = false;
 new MutationObserver(() => {
  if (titlesQueued) return;
  titlesQueued = true;
  queueMicrotask(() => { titlesQueued = false; refreshActionTitles(); });
 }).observe(document.body, { subtree:true, childList:true, characterData:true, attributes:true, attributeFilter:['aria-label'] });
 document.getElementById('dashboard-sidebar')?.addEventListener('transitionend', event => {
  if (event.target.id === 'dashboard-sidebar' && event.propertyName === 'margin-left') window.dispatchEvent(new Event('resize'));
 });
 const confirmation = document.getElementById('portal-confirm-action');
 let pendingAction = null;
 let confirmedForm = null;
 confirmation?.querySelectorAll('[data-confirm-cancel]').forEach(button => button.addEventListener('click', () => confirmation.close()));
 confirmation?.addEventListener('close', () => { pendingAction = null; });
 confirmation?.querySelector('[data-confirm-accept]').addEventListener('click', () => {
  if (!pendingAction) return;
  const {form, submitter} = pendingAction;
  confirmation.close();
  confirmedForm = form;
  try { form.requestSubmit(submitter || undefined); } finally { confirmedForm = null; }
 });
 document.addEventListener('submit', event => {
  const form = event.target;
  const message = event.submitter?.dataset.confirm || form.dataset.confirm;
  if (!message || confirmedForm === form || event.defaultPrevented || !confirmation) return;
  event.preventDefault();
  pendingAction = {form, submitter: event.submitter};
  confirmation.querySelector('#portal-confirm-message').textContent = message;
  confirmation.querySelector('[data-confirm-accept]').textContent = /archive/i.test(message) ? 'Archive' : /remove|delete/i.test(message) ? 'Remove' : 'Confirm';
  confirmation.showModal();
 });
 const container = document.getElementById('portal-feedback-toasts');
 const prepareToast = toast => {
  toast.querySelector('[data-toast-dismiss]').addEventListener('click', () => toast.remove());
  // Errors and warnings stay until dismissed; success pauses while reading or focusing.
  if (!['success', 'info'].includes(toast.dataset.tone)) return;
  let timer;
  const stop = () => clearTimeout(timer);
  const start = () => { stop(); timer = setTimeout(() => toast.remove(), 3000); };
  toast.addEventListener('mouseenter', stop); toast.addEventListener('mouseleave', start);
  toast.addEventListener('focusin', stop); toast.addEventListener('focusout', start);
  start();
 };
 container?.querySelectorAll('.portal-feedback-toast').forEach(prepareToast);
 // Async actions report actual outcomes through this event, never on a mere click.
 window.addEventListener('portal:feedback', event => {
  if (!container || typeof event.detail?.message !== 'string') return;
  const tone = ['success', 'danger', 'warning', 'info'].includes(event.detail.tone) ? event.detail.tone : 'info';
  const toast = document.createElement('div');
  toast.className = `toast show portal-feedback-toast border-${tone}`;
  toast.dataset.tone = tone; toast.setAttribute('role', tone === 'danger' ? 'alert' : 'status'); toast.setAttribute('aria-atomic', 'true');
  const header = document.createElement('div'); header.className = 'toast-header';
  const title = document.createElement('strong'); title.className = `me-auto text-${tone}`;
  title.textContent = {success:'Success', danger:'Action unsuccessful', warning:'Attention', info:'Information'}[tone];
  const close = document.createElement('button'); close.type = 'button'; close.className = 'btn-close'; close.setAttribute('aria-label', 'Dismiss notification'); close.setAttribute('data-toast-dismiss', '');
  const body = document.createElement('div'); body.className = 'toast-body'; body.textContent = event.detail.message;
  header.append(title, close); toast.append(header, body); container.append(toast); prepareToast(toast);
 });
 const clearBusy = () => document.querySelectorAll('form[data-spark-busy]').forEach(form => {
  form.removeAttribute('aria-busy'); delete form.dataset.sparkBusy;
  form.querySelectorAll('[data-spark-loading]').forEach(button => button.removeAttribute('data-spark-loading'));
 });
 document.addEventListener('submit', event => {
  const form=event.target;
  if(!(form instanceof HTMLFormElement)||event.defaultPrevented||form.method.toLowerCase()==='get'||!form.checkValidity())return;
  form.dataset.sparkBusy='true';form.setAttribute('aria-busy','true');
  if(event.submitter)event.submitter.setAttribute('data-spark-loading','true');
 });
 window.addEventListener('pageshow',clearBusy);
})();

// Live profile password checks. Password values stay out of URLs and storage.
(() => {
 const form = document.querySelector('[data-password-validation]');
 if (!form) return;
 const current = form.elements.current_password, password = form.elements.password, confirmation = form.elements.password_confirmation;
 const button = form.querySelector('button[type="submit"]');
 const feedback = form.querySelector('[data-current-feedback]');
 let verified = false, timer, controller, revision = 0;
 const validate = () => {
  const missing = [];
  if (password.value.length < 8) missing.push('8 characters');
  if (!/[A-Z]/.test(password.value)) missing.push('an uppercase letter');
  if (!/[a-z]/.test(password.value)) missing.push('a lowercase letter');
  if (!/[0-9]/.test(password.value)) missing.push('a number');
  if (!/[^A-Za-z0-9]/.test(password.value)) missing.push('a special character');
  const mismatch = confirmation.value !== password.value;
  form.querySelector('[data-strength-feedback]').textContent = password.value && missing.length ? 'Password needs ' + missing.join(', ') + '.' : '';
  form.querySelector('[data-match-feedback]').textContent = confirmation.value && mismatch ? 'Passwords do not match.' : '';
  password.setAttribute('aria-invalid', String(!!password.value && missing.length > 0));
  confirmation.setAttribute('aria-invalid', String(!!confirmation.value && mismatch));
  button.disabled = !verified || !!missing.length || mismatch || !confirmation.value;
 };
 current.addEventListener('input', () => {
  clearTimeout(timer); if (controller) controller.abort();
  const attempt = ++revision;
  verified = false; current.removeAttribute('aria-invalid'); validate();
  feedback.textContent = current.value ? 'Checking current password…' : '';
  if (!current.value) return;
  timer = setTimeout(async () => {
   controller = new AbortController();
   try {
    const response = await fetch(form.dataset.checkUrl, {method:'POST', credentials:'same-origin', signal:controller.signal, headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value}, body:JSON.stringify({current_password:current.value})});
    if (attempt !== revision) return;
    if (!response.ok) throw new Error('check-failed');
    const result = await response.json();
    if (attempt !== revision) return;
    verified = result.valid === true;
    feedback.textContent = verified ? '' : 'The password is incorrect.';
    current.setAttribute('aria-invalid', String(!verified));
   } catch (error) {
    if (attempt !== revision || error.name === 'AbortError') return;
    verified = false; feedback.textContent = 'Unable to verify. Pause, then edit your current password to retry.';
   }
   validate();
  }, 600);
 });
 password.addEventListener('input', validate);
 confirmation.addEventListener('input', validate);
 form.addEventListener('submit', event => { validate(); if (button.disabled) { event.preventDefault(); event.stopImmediatePropagation(); } }, true);
 validate();
})();
