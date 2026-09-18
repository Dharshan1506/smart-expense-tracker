/**
 * Client-Side Form Validations & Interactive Feedback
 * Smart Expense Tracker
 */
document.addEventListener('DOMContentLoaded', () => {

    // 1. Password Confirmation Check on Registration
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', (e) => {
            const passInput = document.getElementById('regPassword') || document.getElementById('password') || registerForm.querySelector('input[name="password"]');
            const confirmPassInput = document.getElementById('regConfirmPassword') || document.getElementById('confirm_password') || registerForm.querySelector('input[name="confirm_password"]');

            if (!passInput || !confirmPassInput) return;

            const pass = passInput.value.trim();
            const confirmPass = confirmPassInput.value.trim();

            if (pass.length < 6) {
                alert('Password must contain at least 6 characters.');
                passInput.focus();
                e.preventDefault();
                return false;
            }

            if (pass !== confirmPass) {
                alert('Passwords do not match. Please re-enter.');
                confirmPassInput.focus();
                e.preventDefault();
                return false;
            }
        });
    }

    // 2. Positive Numerical Amount Sanity Validation
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
