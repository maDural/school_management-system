    </div>
    <script>
        // Inline JavaScript
        function confirmAction(message) {
            return confirm(message);
        }
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            const content = document.querySelector('.content');
            if (content) {
                content.insertBefore(alertDiv, content.firstChild);
            }
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.style.opacity = '0';
                    alertDiv.style.transition = 'opacity 0.5s';
                    setTimeout(() => alertDiv.remove(), 500);
                }
            }, 5000);
        }

        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (form) {
                const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
                let isValid = true;
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.style.borderColor = '#e74c3c';
                        input.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
                        isValid = false;
                    } else {
                        input.style.borderColor = '#2ecc71';
                        input.style.boxShadow = 'none';
                    }
                });
                if (!isValid) {
                    showAlert('Please fill in all required fields', 'error');
                }
                return isValid;
            }
            return true;
        }

        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }

        function printPage() {
            window.print();
        }

        function goBack() {
            window.history.back();
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>