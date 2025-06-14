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
                    importContainer.show();
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

    // Handle backup import
    $('#ez-translate-import-backup').on('click', function(e) {
        e.preventDefault();
        if (!backupData) return;

        const selectedLanguages = [];
        $('input[name="import_languages[]"]:checked').each(function() {
            selectedLanguages.push($(this).val());
        });

        if (selectedLanguages.length === 0) {
            alert('Por favor, selecciona al menos un idioma para importar.');
            return;
        }

        const importData = {
            action: 'ez_translate_import_backup',
            nonce: ezTranslateBackup.importNonce,
            backup_data: JSON.stringify(backupData),
            selected_languages: selectedLanguages,
            import_default_metadata: $('#import_default_metadata').is(':checked')
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
});
