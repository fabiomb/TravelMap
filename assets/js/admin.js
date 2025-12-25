// Scripts personalizados para el panel de administración

$(document).ready(function() {
    // Auto-cerrar alertas después de 5 segundos
    setTimeout(function() {
        $('.alert:not(.alert-permanent)').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);

    // Confirmación antes de eliminar
    $('.btn-delete, .delete-action').on('click', function(e) {
        if (!confirm('¿Estás seguro de que deseas eliminar este elemento? Esta acción no se puede deshacer.')) {
            e.preventDefault();
            return false;
        }
    });

    // Tooltip de Bootstrap (si se usan)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
