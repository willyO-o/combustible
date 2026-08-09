import Swal from "sweetalert2";
import { toast } from "vue3-toastify";

export const showToast = (message, type = "success") => {
    toast[type](message, {
        theme: document.querySelector('html').getAttribute('data-theme-mode') || 'light',
        icon: true,
        hideProgressBar: false,
        autoClose: 5000,
        position: "top-right",
        dangerouslyHTMLString: true,
        // progress:
    });
};

export const showError = (errors)=>{
    let message = '';
    if (typeof errors === 'string') {
        message = errors;
    } else if (Array.isArray(errors)) {
        message = errors.join('<br>');
    } else if (typeof errors === 'object' && errors !== null) {
        message = Object.values(errors).map((error) => `<p class=" text-danger mb-0">${error}</p>`).join('');
    }

    // showToast(message, 'error');
    Swal.fire({
        title: 'Error',
        html: message,
        icon: 'error',
        confirmButtonText: 'Aceptar'
    });
}

export const confirm = async (
    message = "¿Está seguro?",
    title = "Confirmación",
    txtBtn = "Si, eliminar",
) => {
    const result = await Swal.fire({
        title: title,
        html: message,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: txtBtn,
        cancelButtonText: "Cancelar",
    });

    return result.isConfirmed;
};


