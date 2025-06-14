jQuery(document).ready(function($) {
    const backupForm = $('#ez-translate-backup-form');
    const previewContainer = $('#backup-preview-container');
    const importContainer = $('#backup-import-container');
    const tableContainer = $('#languages-table-container');
    const importSpinner = $('.import-spinner');
    let backupData = null;

    // Handle backup file selection and preview
    backupForm.on('change', 'input[type="file"]', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('action', 'ez_translate_preview_backup');
        formData.append('backup_file', file);
        formData.append('nonce', ezTranslateBackup.previewNonce);

        importSpinner.show();
        previewContainer.html('');
        importContainer.hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    previewContainer.html(response.data.preview_html);
                    backupData = response.data.backup_data;
                    
                    // Check if there are any changes to import
                    const hasChanges = response.data.preview_html.includes('changes-preview') || 
                                     response.data.preview_html.includes('new-language') ||
                                     response.data.preview_html.includes('default-metadata-changes');
                    
                    if (hasChanges) {
                        importContainer.show();
                        // Add a message indicating changes were found
                        previewContainer.prepend(
                            '<div class="notice notice-info">' +
                            '<p>Se han detectado cambios en los metadatos. Por favor, revisa los cambios y haz clic en "Confirmar Importación" para proceder.</p>' +
                            '</div>'
                        );

                        // Highlight SEO changes
                        $('.field-change').each(function() {
                            const fieldName = $(this).find('strong').text().toLowerCase();
                            if (fieldName.includes('title') || fieldName.includes('description')) {
                                $(this).addClass('seo-change');
                            }
                        });
                    } else {
                        importContainer.hide();
                        previewContainer.prepend(
                            '<div class="notice notice-warning">' +
                            '<p>No se detectaron cambios para importar.</p>' +
                            '</div>'
                        );
                    }
                } else {
                    previewContainer.html(`<div class="notice notice-error"><p>${response.data}</p></div>`);
                }
            },
            error: function() {
                previewContainer.html('<div class="notice notice-error"><p>Error al previsualizar el backup.</p></div>');
            },
            complete: function() {
                importSpinner.hide();
            }
        });
    });

    // Handle import button click
    $('#ez-translate-import-backup').on('click', function() {
        if (!backupData) return;

        const selectedLanguages = [];
        $('input[name="selected_languages[]"]:checked').each(function() {
            selectedLanguages.push($(this).val());
        });

        const importDefaultMetadata = $('#import_default_metadata_checkbox').is(':checked');

        const importData = {
            action: 'ez_translate_import_backup',
            nonce: ezTranslateBackup.importNonce,
            backup_data: JSON.stringify(backupData),
            selected_languages: selectedLanguages,
            import_default_metadata: importDefaultMetadata
        };

        importSpinner.show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: importData,
            success: function(response) {
                if (response.success) {
                    // Update the languages table
                    tableContainer.html(response.data.table_html);
                    
                    // Show success message
                    previewContainer.html(`
                        <div class="notice notice-success">
                            <p>${response.data.message}</p>
                            <p>Los metadatos SEO han sido actualizados correctamente.</p>
                        </div>
                    `);
                    
                    // Clear the form
                    backupForm[0].reset();
                    importContainer.hide();
                    backupData = null;
                } else {
                    previewContainer.html(`<div class="notice notice-error"><p>${response.data}</p></div>`);
                }
            },
            error: function() {
                previewContainer.html('<div class="notice notice-error"><p>Error al importar el backup.</p></div>');
            },
            complete: function() {
                importSpinner.hide();
            }
        });
    });

    // Add hover effect for SEO changes
    $(document).on('mouseenter', '.seo-change', function() {
        $(this).find('.change-details').show();
    }).on('mouseleave', '.seo-change', function() {
        $(this).find('.change-details').hide();
    });
});
