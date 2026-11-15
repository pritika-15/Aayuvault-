// ===== AayuVault Login – script.js =====

const form        = document.getElementById('loginForm');
const usernameEl  = document.getElementById('username');
const passwordEl  = document.getElementById('password');
const togglePwBtn = document.getElementById('togglePw');
const submitBtn   = document.getElementById('submitBtn');
const spinner     = document.getElementById('spinner');
const serverMsg   = document.getElementById('serverMsg');
const btnText     = submitBtn.querySelector('.btn-text');

// ---- Toggle password visibility ----
togglePwBtn.addEventListener('click', () => {
  const isText = passwordEl.type === 'text';
  passwordEl.type = isText ? 'password' : 'text';
  togglePwBtn.innerHTML = isText
    ? `<svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
         <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
         <circle cx="12" cy="12" r="3"/>
       </svg>`
    : `<svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
         <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
         <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
         <line x1="1" y1="1" x2="23" y2="23"/>
       </svg>`;
});

// ---- Real-time validation helpers ----
function setError(inputEl, msgEl, msg) {
  inputEl.classList.add('is-error');
  document.getElementById(msgEl).textContent = msg;
}

function clearError(inputEl, msgEl) {
  inputEl.classList.remove('is-error');
  document.getElementById(msgEl).textContent = '';
}

usernameEl.addEventListener('input', () => clearError(usernameEl, 'usernameError'));
passwordEl.addEventListener('input', () => clearError(passwordEl, 'passwordError'));

// ---- Client-side validation ----
function validate() {
  let valid = true;
  const uVal = usernameEl.value.trim();
  const pVal = passwordEl.value;

  if (!uVal) {
    setError(usernameEl, 'usernameError', 'Please enter your username or email.');
    valid = false;
  } else if (uVal.includes('@') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(uVal)) {
    setError(usernameEl, 'usernameError', 'Please enter a valid email address.');
    valid = false;
  }

  if (!pVal) {
    setError(passwordEl, 'passwordError', 'Password is required.');
    valid = false;
  } else if (pVal.length < 6) {
    setError(passwordEl, 'passwordError', 'Password must be at least 6 characters.');
    valid = false;
  }

  return valid;
}

// ---- Form submit (AJAX to login.php) ----
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  serverMsg.className = 'server-msg';
  serverMsg.textContent = '';

  if (!validate()) return;

  // Show loading state
  submitBtn.disabled = true;
  btnText.textContent = 'Logging in…';
  spinner.hidden = false;

  try {
    const formData = new FormData(form);

    const response = await fetch('../pages/auth.php', {
      method: 'POST',
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      serverMsg.classList.add('success');
      serverMsg.textContent = '✅ ' + (data.message || 'Login successful! Redirecting…');
      setTimeout(() => {
        window.location.href = data.redirect || 'dashboard.php';
      }, 1000);
    } else {
      serverMsg.classList.add('error');
      serverMsg.textContent = '⚠️ ' + (data.message || 'Invalid credentials. Please try again.');
      submitBtn.disabled = false;
      btnText.textContent = 'Log In to MyVault';
      spinner.hidden = true;
    }

  } catch (err) {
    // Network error – fallback to normal form submit
    serverMsg.classList.add('error');
    serverMsg.textContent = '⚠️ Network error. Please check your connection.';
    submitBtn.disabled = false;
    btnText.textContent = 'Log In to MyVault';
    spinner.hidden = true;
  }
});