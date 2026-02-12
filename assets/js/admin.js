jQuery(document).ready(function($) {
    
    let storeIndex = $('#msw-stores-tbody tr').length;

    // Add store row
    $('#msw-add-store').on('click', function() {
        const template = $('#tmpl-msw-store-row').html();
        const html = template.replace(/\{\{data\.index\}\}/g, storeIndex);
        $('#msw-stores-tbody').append(html);
        storeIndex++;
    });

    // Remove store row
    $(document).on('click', '.msw-remove-store', function() {
        if (confirm('¿Estás seguro de eliminar esta tienda?')) {
            $(this).closest('tr').remove();
        }
    });

});