// Initialize SweetAlert notifications
document.addEventListener("DOMContentLoaded", function() {
    if (typeof notification_message !== 'undefined' && notification_message !== '') {
        Swal.fire({
            icon: notification_type, // 'success', 'error', 'warning', 'info'
            title: notification_title,
            html: notification_message,
            confirmButtonText: 'Tutup'
        });
    }
});
