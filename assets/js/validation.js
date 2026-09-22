/**
 * Client-Side Form Validations & Interactive Password Strength System
 * Smart Expense Tracker - Modern Fintech Design
 */

// Global Toggle for Password Visibility
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {

    // 1. Interactive Password Strength Indicator & Live Criteria
    const regPassword = document.getElementById('regPassword');
    const regConfirm = document.getElementById('regConfirmPassword');
    const bar1 = document.getElementById('strengthBar1');
    const bar2 = document.getElementById('strengthBar2');
    const bar3 = document.getElementById('strengthBar3');
    const bar4 = document.getElementById('strengthBar4');
    const strengthLabel = document.getElementById('strengthLabelText');
    const critLength = document.getElementById('critLength');
    const critMatch = document.getElementById('critMatch');
    const critComplexity = document.getElementById('critComplexity');

    function evaluatePasswordStrength(val) {
        let score = 0;
        if (!val) return 0;

        if (val.length >= 6) score++;
        if (val.length >= 10) score++;
        if (/[0-9]/.test(val) && /[a-zA-Z]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        return Math.min(score, 4);
    }

    function updateStrengthUI() {
        if (!regPassword) return;
        const val = regPassword.value;
        const confirmVal = regConfirm ? regConfirm.value : '';
        const score = evaluatePasswordStrength(val);

        // Update criteria badges
        if (critLength) {
            if (val.length >= 6) {
                critLength.classList.add('met');
                critLength.querySelector('i').className = 'fa-solid fa-circle-check';
            } else {
                critLength.classList.remove('met');
                critLength.querySelector('i').className = 'fa-regular fa-circle';
            }
        }

        if (critComplexity) {
            if (/[0-9]/.test(val) && /[a-zA-Z]/.test(val)) {
                critComplexity.classList.add('met');
                critComplexity.querySelector('i').className = 'fa-solid fa-circle-check';
            } else {
                critComplexity.classList.remove('met');
                critComplexity.querySelector('i').className = 'fa-regular fa-circle';
            }
        }

        if (critMatch) {
            if (confirmVal.length > 0 && val === confirmVal) {
                critMatch.classList.add('met');
                critMatch.querySelector('i').className = 'fa-solid fa-circle-check';
            } else {
                critMatch.classList.remove('met');
                critMatch.querySelector('i').className = 'fa-regular fa-circle';
            }
        }

        // Color bars
        const bars = [bar1, bar2, bar3, bar4];
        bars.forEach(b => { if (b) b.style.backgroundColor = '#e2e8f0'; });

        let labelText = 'Too weak';
        let labelColor = '#94a3b8';
        let barColor = '#ef4444';

        if (val.length === 0) {
            labelText = 'Password strength';
            labelColor = '#94a3b8';
        } else if (score === 1) {
            labelText = 'Weak (min 6 chars)';
            labelColor = '#ef4444';
            barColor = '#ef4444';
            if (bar1) bar1.style.backgroundColor = barColor;
        } else if (score === 2) {
            labelText = 'Fair';
            labelColor = '#f59e0b';
            barColor = '#f59e0b';
            if (bar1) bar1.style.backgroundColor = barColor;
            if (bar2) bar2.style.backgroundColor = barColor;
        } else if (score === 3) {
            labelText = 'Good';
            labelColor = '#3b82f6';
            barColor = '#3b82f6';
            if (bar1) bar1.style.backgroundColor = barColor;
            if (bar2) bar2.style.backgroundColor = barColor;
            if (bar3) bar3.style.backgroundColor = barColor;
        } else if (score >= 4) {
            labelText = 'Strong & Secure';
            labelColor = '#10b981';
            barColor = '#10b981';
            bars.forEach(b => { if (b) b.style.backgroundColor = barColor; });
        }

        if (strengthLabel) {
            strengthLabel.textContent = labelText;
            strengthLabel.style.color = labelColor;
        }
    }

    if (regPassword) {
        regPassword.addEventListener('input', updateStrengthUI);
    }
    if (regConfirm) {
        regConfirm.addEventListener('input', updateStrengthUI);
    }

    // 2. Registration Form Submission Validation
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', (e) => {
            const nameInput = document.getElementById('regName');
            const emailInput = document.getElementById('regEmail');
            const passInput = document.getElementById('regPassword');
            const confirmPassInput = document.getElementById('regConfirmPassword');

            if (nameInput && nameInput.value.trim().length < 2) {
                alert('Please enter your full name (minimum 2 characters).');
                nameInput.focus();
                e.preventDefault();
                return false;
            }

            if (emailInput && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
                alert('Please provide a valid email address.');
                emailInput.focus();
                e.preventDefault();
                return false;
            }

            if (passInput && passInput.value.trim().length < 6) {
                alert('Password must contain at least 6 characters.');
                passInput.focus();
                e.preventDefault();
                return false;
            }

            if (passInput && confirmPassInput && passInput.value !== confirmPassInput.value) {
                alert('Passwords do not match. Please verify both passwords.');
                confirmPassInput.focus();
                e.preventDefault();
                return false;
            }
        });
    }

    // 3. Login Form Submission Validation
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            const emailInput = document.getElementById('loginEmail');
            const passInput = document.getElementById('loginPassword');

            if (emailInput && !emailInput.value.trim()) {
                alert('Please enter your email address.');
                emailInput.focus();
                e.preventDefault();
                return false;
            }

            if (passInput && !passInput.value) {
                alert('Please enter your account password.');
                passInput.focus();
                e.preventDefault();
                return false;
            }
        });
    }

    // 4. Positive Numerical Amount Sanity Validation
    const amountInputs = document.querySelectorAll('input[name="amount"], input[name="budget_amount"], input[name="target_amount"], input[name="deposit_amount"]');
    amountInputs.forEach(input => {
        input.addEventListener('input', () => {
            const val = parseFloat(input.value);
            if (isNaN(val) || val <= 0) {
                input.style.borderColor = 'var(--danger, #ef4444)';
            } else {
                input.style.borderColor = '';
            }
        });
    });

});
