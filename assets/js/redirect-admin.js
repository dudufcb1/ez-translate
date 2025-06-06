/**
 * EZ Translate - Redirect Admin JavaScript
 *
 * @package EZTranslate
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initRedirectAdmin();
    });

    /**
     * Initialize redirect admin functionality
     */
    function initRedirectAdmin() {
        initModalHandlers();
        initBulkActions();
        initActionButtons();
        initTableActions();
        initCatchAllSettings();
    }

    /**
     * Initialize modal handlers
     */
    function initModalHandlers() {
        // Add new redirect button
        $('#add-new-redirect').on('click', function() {
            resetModal();
            $('#modal-title').text('Add New Redirect');
            $('#submit-redirect').val('Add Redirect');
            $('#add-redirect-modal').show();
        });

        // Close modal handlers
        $('.ez-translate-modal .close, .cancel-modal').on('click', function() {
            $('.ez-translate-modal').hide();
        });

        // Close modal when clicking outside
        $('.ez-translate-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });

        // Handle redirect method change
        $('input[name="redirect_method"]').on('change', function() {
            toggleRedirectMethod();
        });

        // Handle destination type change
        $('input[name="destination_type"]').on('change', function() {
            toggleDestinationType();
        });

        // Handle add redirect form submission
        $('#add-redirect-form').on('submit', function(e) {
            e.preventDefault();
            handleAddRedirect();
        });
    }

    /**
     * Initialize bulk actions
     */
    function initBulkActions() {
        // Select all checkbox
        $('#cb-select-all-1').on('change', function() {
            $('input[name="redirect_ids[]"]').prop('checked', this.checked);
        });

        // Individual checkboxes
        $(document).on('change', 'input[name="redirect_ids[]"]', function() {
            updateSelectAllCheckbox();
        });

        // Bulk action form submission
        $('form').on('submit', function(e) {
            var action = $('#bulk-action-selector-top').val();
            if (action && action !== '') {
                var checkedBoxes = $('input[name="redirect_ids[]"]:checked');
                if (checkedBoxes.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one redirect to perform this action.');
                    return false;
                }

                if (action === 'delete') {
                    if (!confirm(ezTranslateRedirect.strings.confirm_bulk_delete)) {
                        e.preventDefault();
                        return false;
                    }
                }

                // Set the bulk action value
                $('input[name="bulk_action"]').val(action);
            }
        });
    }

    /**
     * Initialize action buttons
     */
    function initActionButtons() {
        // Check WordPress redirects
        $('#check-wp-redirects').on('click', function() {
            var $button = $(this);
            checkWordPressRedirects($button);
        });

        // Cleanup old redirects
        $('#cleanup-old-redirects').on('click', function() {
            var $button = $(this);
            if (confirm('Are you sure you want to cleanup old redirects? This action cannot be undone.')) {
                cleanupOldRedirects($button);
            }
        });

        // Test redirect system
        $('#test-redirect-system').on('click', function() {
            var $button = $(this);
            testRedirectSystem($button);
        });

        // Debug edit system
        $('#debug-edit-system').on('click', function() {
            var $button = $(this);
            debugEditSystem($button);
        });
    }

    /**
     * Initialize table actions
     */
    function initTableActions() {
        // Edit redirect buttons
        $(document).on('click', '.edit-redirect', function() {
            var redirectId = $(this).data('id');
            editRedirect(redirectId);
        });

        // Delete redirect buttons
        $(document).on('click', '.delete-redirect', function() {
            var redirectId = $(this).data('id');
            if (confirm(ezTranslateRedirect.strings.confirm_delete)) {
                deleteRedirect(redirectId);
            }
        });
    }

    /**
     * Handle add redirect form submission
     */
    function handleAddRedirect() {
        var redirectId = $('input[name="redirect_id"]').val();
        var method = $('input[name="redirect_method"]:checked').val();
        var isEdit = redirectId && redirectId !== '';

        var formData = {
            action: isEdit ? 'ez_translate_update_redirect' : 'ez_translate_add_redirect',
            nonce: ezTranslateRedirect.nonce,
            redirect_id: redirectId,
            redirect_method: method,
            redirect_type: $('select[name="redirect_type"]').val()
        };

        if (method === 'manual') {
            formData.old_url = $('input[name="old_url"]').val();
            formData.new_url = $('input[name="new_url"]').val();
        } else {
            formData.source_post_id = $('select[name="source_post_id"]').val();

            var destinationType = $('input[name="destination_type"]:checked').val();
            formData.destination_type = destinationType;

            if (destinationType === 'post') {
                formData.destination_post_id = $('select[name="destination_post_id"]').val();
            } else if (destinationType === 'url') {
                formData.destination_url = $('input[name="destination_url"]').val();
            }
        }

        $.ajax({
            url: ezTranslateRedirect.ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                $('#add-redirect-form').addClass('ez-translate-loading');
            },
            success: function(response) {
                if (response.success) {
                    var message = isEdit ? 'Redirect updated successfully!' : 'Redirect added successfully!';
                    showMessage(message, 'success');
                    $('#add-redirect-modal').hide();
                    $('#add-redirect-form')[0].reset();
                    location.reload(); // Reload to show changes
                } else {
                    showMessage(response.data || 'Failed to save redirect.', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while saving the redirect.', 'error');
            },
            complete: function() {
                $('#add-redirect-form').removeClass('ez-translate-loading');
            }
        });
    }

    /**
     * Check WordPress redirects
     */
    function checkWordPressRedirects($button) {
        var originalText = $button.text();
        
        $.ajax({
            url: ezTranslateRedirect.ajaxurl,
            type: 'POST',
            data: {
                action: 'ez_translate_check_wp_redirects',
                nonce: ezTranslateRedirect.nonce
            },
            beforeSend: function() {
                $button.addClass('checking').text('Checking...');
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var message = 'Check completed! Found ' + data.wp_auto_found + ' WordPress automatic redirects out of ' + data.checked + ' checked.';
                    showMessage(message, 'success');
                    
                    // Reload page to show updated data
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage(response.data || 'Failed to check redirects.', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while checking redirects.', 'error');
            },
            complete: function() {
                $button.removeClass('checking').text(originalText);
            }
        });
    }

    /**
     * Debug edit system
     */
    function debugEditSystem($button) {
        console.log('=== DEBUG EDIT SYSTEM ===');
        console.log('ezTranslateRedirect object:', ezTranslateRedirect);
        console.log('Edit buttons found:', $('.edit-redirect').length);

        $('.edit-redirect').each(function(i, btn) {
            var $btn = $(btn);
            var redirectId = $btn.data('id');
            console.log('Edit button ' + i + ':', {
                element: btn,
                dataId: redirectId,
                dataIdType: typeof redirectId
            });
        });

        // Test with first redirect if available
        var $firstEditBtn = $('.edit-redirect').first();
        if ($firstEditBtn.length > 0) {
            var testId = $firstEditBtn.data('id');
            console.log('Testing with first redirect ID:', testId);

            // Test AJAX call manually
            $.ajax({
                url: ezTranslateRedirect.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ez_translate_get_redirect',
                    nonce: ezTranslateRedirect.nonce,
                    redirect_id: testId
                },
                success: function(response) {
                    console.log('Manual AJAX test SUCCESS:', response);
                    showMessage('Debug test successful! Check console for details.', 'success');
                },
                error: function(xhr, status, error) {
                    console.error('Manual AJAX test FAILED:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    showMessage('Debug test failed! Check console for details.', 'error');
                }
            });
        } else {
            console.log('No edit buttons found');
            showMessage('No edit buttons found on page.', 'error');
        }
    }

    /**
     * Test redirect system
     */
    function testRedirectSystem($button) {
        var originalText = $button.text();

        $.ajax({
            url: ezTranslateRedirect.ajaxurl,
            type: 'POST',
            data: {
                action: 'ez_translate_test_redirect_system',
                nonce: ezTranslateRedirect.nonce
            },
            beforeSend: function() {
                $button.addClass('checking').text('Testing...');
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var message = 'Test completed! Created ' + data.created + ' test redirects. Check the table below.';
                    showMessage(message, 'success');

                    // Reload page to show new redirects
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage(response.data || 'Test failed.', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred during testing.', 'error');
            },
            complete: function() {
                $button.removeClass('checking').text(originalText);
            }
        });
    }

    /**
     * Cleanup old redirects
     */
    function cleanupOldRedirects($button) {
        var originalText = $button.text();
        
        $.ajax({
            url: ezTranslateRedirect.ajaxurl,
            type: 'POST',
            data: {
                action: 'ez_translate_cleanup_redirects',
                nonce: ezTranslateRedirect.nonce
            },
            beforeSend: function() {
                $button.addClass('checking').text('Cleaning...');
            },
            success: function(response) {
                if (response.success) {
                    var message = 'Cleanup completed! Removed ' + response.data + ' old redirect records.';
                    showMessage(message, 'success');
                    
                    // Reload page to show updated data
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage(response.data || 'Failed to cleanup redirects.', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred during cleanup.', 'error');
            },
            complete: function() {
                $button.removeClass('checking').text(originalText);
            }
        });
    }

    /**
     * Reset modal to default state
     */
    function resetModal() {
        $('#add-redirect-form')[0].reset();
        $('input[name="redirect_id"]').val('');
        $('input[name="redirect_method"][value="manual"]').prop('checked', true);
        $('input[name="destination_type"][value="post"]').prop('checked', true);

        // Remove any existing current info div
        $('.modal-content .current-redirect-info').remove();

        // Reset title
        $('#modal-title').html('Add New Redirect');

        toggleRedirectMethod();
        toggleDestinationType();
    }

    /**
     * Toggle redirect method sections
     */
    function toggleRedirectMethod() {
        var method = $('input[name="redirect_method"]:checked').val();

        if (method === 'manual') {
            $('#manual-url-section, #manual-new-url-section').show();
            $('#post-selection-section, #destination-section').hide();
            $('#destination-post-section, #destination-url-section').hide();
        } else {
            $('#manual-url-section, #manual-new-url-section').hide();
            $('#post-selection-section, #destination-section').show();
            toggleDestinationType();
        }
    }

    /**
     * Toggle destination type sections
     */
    function toggleDestinationType() {
        var type = $('input[name="destination_type"]:checked').val();

        // Hide all destination sections first
        $('#destination-post-section, #destination-url-section').hide();

        console.log('toggleDestinationType called with type:', type);

        if (type === 'post') {
            $('#destination-post-section').show();
            $('select[name="redirect_type"]').val('301');
            console.log('Showing destination post section');
        } else if (type === 'url') {
            $('#destination-url-section').show();
            $('select[name="redirect_type"]').val('301');
            console.log('Showing destination URL section');
        } else if (type === 'gone') {
            // For Gone (410), hide both destination sections
            $('select[name="redirect_type"]').val('410');
            console.log('Setting Gone (410) - hiding all destination sections');
        }
    }

    /**
     * Edit redirect
     */
    function editRedirect(redirectId) {
        console.log('Editing redirect with ID:', redirectId);
        console.log('AJAX URL:', ezTranslateRedirect.ajaxurl);
        console.log('Nonce:', ezTranslateRedirect.nonce);

        // Get redirect data via AJAX
        $.ajax({
            url: ezTranslateRedirect.ajaxurl,
            type: 'POST',
            data: {
                action: 'ez_translate_get_redirect',
                nonce: ezTranslateRedirect.nonce,
                redirect_id: redirectId
            },
            beforeSend: function() {
                console.log('Sending AJAX request for redirect ID:', redirectId);
            },
            success: function(response) {
                console.log('AJAX response:', response);

                if (response.success) {
                    var redirect = response.data;
                    console.log('Redirect data received:', redirect);
                    populateEditModal(redirect);
                    $('#modal-title').text('Edit Redirect');
                    $('#submit-redirect').val('Update Redirect');
                    $('#add-redirect-modal').show();
                } else {
                    console.error('AJAX error:', response.data);
                    showMessage(response.data || 'Failed to load redirect data.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX request failed:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                showMessage('An error occurred while loading redirect data: ' + error, 'error');
            }
        });
    }

    /**
     * Populate edit modal with redirect data
     */
    function populateEditModal(redirect) {
        $('input[name="redirect_id"]').val(redirect.id);

        console.log('Populating modal with redirect data:', redirect);

        if (redirect.post_id && redirect.post_id > 0) {
            // Post-based redirect
            $('input[name="redirect_method"][value="post_selection"]').prop('checked', true);
            $('select[name="source_post_id"]').val(redirect.post_id);

            // Determine destination type based on redirect data
            if (redirect.redirect_type === '410') {
                // Gone (410) - marked as deleted
                $('input[name="destination_type"][value="gone"]').prop('checked', true);
                console.log('Setting destination type to Gone (410) - post marked as deleted');
            } else if (redirect.destination_post_id && redirect.destination_post_id > 0) {
                // Redirect to another post
                $('input[name="destination_type"][value="post"]').prop('checked', true);
                $('select[name="destination_post_id"]').val(redirect.destination_post_id);
                console.log('Setting destination type to Post, ID:', redirect.destination_post_id);
            } else if (redirect.new_url && redirect.new_url !== null && redirect.new_url !== '') {
                // Redirect to custom URL
                $('input[name="destination_type"][value="url"]').prop('checked', true);
                $('input[name="destination_url"]').val(redirect.new_url);
                console.log('Setting destination type to URL:', redirect.new_url);
            } else {
                // Default: redirect to itself (same post)
                $('input[name="destination_type"][value="post"]').prop('checked', true);
                $('select[name="destination_post_id"]').val(redirect.post_id); // Same post as source
                console.log('Default: Setting destination to same post (self-redirect), ID:', redirect.post_id);
            }
        } else {
            // Manual redirect
            $('input[name="redirect_method"][value="manual"]').prop('checked', true);
            $('input[name="old_url"]').val(redirect.old_url);
            $('input[name="new_url"]').val(redirect.new_url || '');
        }

        $('select[name="redirect_type"]').val(redirect.redirect_type);

        // Update the interface
        toggleRedirectMethod();
        toggleDestinationType();

        // Log final state for debugging
        console.log('Modal populated with redirect data:', {
            id: redirect.id,
            redirect_type: redirect.redirect_type,
            post_id: redirect.post_id,
            destination_post_id: redirect.destination_post_id,
            new_url: redirect.new_url,
            selected_method: $('input[name="redirect_method"]:checked').val(),
            selected_destination: $('input[name="destination_type"]:checked').val()
        });

        // Add some visual indication that we're editing
        $('#modal-title').html('Edit Redirect <small>(ID: ' + redirect.id + ')</small>');

        // Show current URLs for reference
        var currentInfo = '<div class="current-redirect-info" style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 4px;">';
        currentInfo += '<strong>Current Redirect:</strong><br>';
        currentInfo += '<strong>From:</strong> ' + redirect.old_url + '<br>';

        // Show destination based on type
        if (redirect.redirect_type === '410') {
            currentInfo += '<strong>To:</strong> <em>Gone (410) - Marked as deleted</em><br>';
        } else if (redirect.destination_post_id && redirect.destination_post_id > 0) {
            currentInfo += '<strong>To:</strong> Another Post (ID: ' + redirect.destination_post_id + ')<br>';
        } else if (redirect.new_url) {
            currentInfo += '<strong>To:</strong> ' + redirect.new_url + '<br>';
        } else {
            currentInfo += '<strong>To:</strong> <em>Same post (self-redirect)</em><br>';
        }

        currentInfo += '<strong>Type:</strong> ' + redirect.redirect_type + ' - ' + redirect.change_type + '<br>';
        currentInfo += '<em>You can change where this redirect points to using the options below.</em>';
        currentInfo += '</div>';

        // Insert current info after the title
        $('.modal-content h2').after(currentInfo);
    }

    /**
     * Delete redirect
     */
    function deleteRedirect(redirectId) {
        $.ajax({
            url: ezTranslateRedirect.ajaxurl,
            type: 'POST',
            data: {
                action: 'ez_translate_delete_redirect',
                nonce: ezTranslateRedirect.nonce,
                redirect_id: redirectId
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Redirect deleted successfully!', 'success');
                    // Remove the row from table
                    $('input[value="' + redirectId + '"]').closest('tr').fadeOut(function() {
                        $(this).remove();
                        updateSelectAllCheckbox();
                    });
                } else {
                    showMessage(response.data || 'Failed to delete redirect.', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while deleting the redirect.', 'error');
            }
        });
    }

    /**
     * Update select all checkbox state
     */
    function updateSelectAllCheckbox() {
        var totalCheckboxes = $('input[name="redirect_ids[]"]').length;
        var checkedCheckboxes = $('input[name="redirect_ids[]"]:checked').length;
        
        $('#cb-select-all-1').prop('checked', totalCheckboxes > 0 && checkedCheckboxes === totalCheckboxes);
    }

    /**
     * Initialize catch-all settings handlers
     */
    function initCatchAllSettings() {
        // Handle destination type change for catch-all settings
        $('input[name="catchall_destination_type"]').on('change', function() {
            toggleCatchAllDestination();
        });

        // Initialize on page load
        toggleCatchAllDestination();
    }

    /**
     * Toggle catch-all destination sections
     */
    function toggleCatchAllDestination() {
        var type = $('input[name="catchall_destination_type"]:checked').val();

        // Hide all sections first
        $('#catchall-page-section, #catchall-url-section').hide();

        // Show relevant section
        if (type === 'page') {
            $('#catchall-page-section').show();
        } else if (type === 'url') {
            $('#catchall-url-section').show();
        }
        // 'home' type doesn't need additional fields
    }

    /**
     * Show message to user
     */
    function showMessage(message, type) {
        var $messageDiv = $('<div class="ez-translate-message ' + type + '">' + message + '</div>');
        $('.wrap h1').after($messageDiv);

        // Auto-hide after 5 seconds
        setTimeout(function() {
            $messageDiv.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

})(jQuery);
