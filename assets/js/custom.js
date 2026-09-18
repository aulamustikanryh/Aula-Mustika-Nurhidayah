document.addEventListener('DOMContentLoaded', function () {
    // Validasi form
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Format currency
    const currencyInputs = document.querySelectorAll('.currency');
    currencyInputs.forEach(input => {
        input.addEventListener('input', function () {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value) {
                value = parseInt(value).toLocaleString('id-ID');
                this.value = value;
            }
        });
        input.addEventListener('blur', function () {
            if (this.value && !isNaN(this.value.replace(/\./g, ''))) {
                this.value = parseInt(this.value.replace(/\./g, '')).toLocaleString('id-ID');
            }
        });
    });
});