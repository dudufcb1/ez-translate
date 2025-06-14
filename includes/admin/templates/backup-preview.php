<?php

/**
 * Template for displaying backup preview
 * 
 * @package EZTranslate
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$comparison = $this->backup_comparison;
?>

<div class="backup-preview">
    <h3><?php esc_html_e('Backup Preview', 'ez-translate'); ?></h3>

    <?php if (
        empty($comparison['languages']['new']) &&
        empty($comparison['languages']['existing']) &&
        empty($comparison['default_metadata']['changes'])
    ): ?>

        <div class="notice notice-warning">
            <p><?php esc_html_e('No changes detected in the backup file.', 'ez-translate'); ?></p>
        </div>

    <?php else: ?>

        <!-- Summary Section -->
        <div class="summary-section">
            <h4><?php esc_html_e('Summary', 'ez-translate'); ?></h4>
            <ul>

                <li><?php
                    printf(
                        /* translators: %d is the number of languages in the backup */
                        esc_html__('Total languages in backup: %d', 'ez-translate'),
                        absint($comparison['summary']['total_backup_languages'])
                    ); ?></li>
                <li><?php
                    printf(
                        /* translators: %d is the number of new languages to create */
                        esc_html__('New languages to create: %d', 'ez-translate'),
                        absint($comparison['summary']['new_languages_count'])
                    ); ?></li>
                <li><?php
                    printf(
                        /* translators: %d is the number of languages to update */
                        esc_html__('Languages to update: %d', 'ez-translate'),
                        absint($comparison['summary']['updated_languages_count'])
                    ); ?></li>
            </ul>
        </div>

        <!-- New Languages Section -->
        <?php if (!empty($comparison['languages']['new'])): ?>
            <div class="new-languages-section">
                <h4><?php esc_html_e('New Languages', 'ez-translate'); ?></h4>
                <ul class="language-list">
                    <?php foreach ($comparison['languages']['new'] as $language): ?>
                        <li>
                            <label>
                                <input type="checkbox" name="selected_languages[]" value="<?php echo esc_attr($language['code']); ?>" checked>
                                <?php echo esc_html($language['name']); ?> (<?php echo esc_html($language['code']); ?>)
                            </label>
                            <?php if (!empty($language['data'])): ?>
                                <div class="language-details">
                                    <strong><?php esc_html_e('SEO Data:', 'ez-translate'); ?></strong>
                                    <ul>
                                        <li><?php /* translators: %s is the site title */ printf(esc_html__('Title: %s', 'ez-translate'), esc_html($language['data']['site_title'] ?? '')); ?></li>
                                        <li><?php /* translators: %s is the site description */ printf(esc_html__('Description: %s', 'ez-translate'), esc_html($language['data']['site_description'] ?? '')); ?></li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Existing Languages Section -->
        <?php if (!empty($comparison['languages']['existing'])): ?>
            <div class="existing-languages-section">
                <h4><?php esc_html_e('Languages to Update', 'ez-translate'); ?></h4>
                <ul class="language-list">
                    <?php foreach ($comparison['languages']['existing'] as $language): ?>
                        <li>
                            <label>
                                <input type="checkbox" name="selected_languages[]" value="<?php echo esc_attr($language['code']); ?>" checked>
                                <?php echo esc_html($language['name']); ?> (<?php echo esc_html($language['code']); ?>)
                            </label>
                            <?php if (!empty($language['differences'])): ?>
                                <div class="changes-preview">
                                    <?php foreach ($language['differences'] as $field => $values): ?>
                                        <div class="field-change">
                                            <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $field))); ?>:</strong>
                                            <div class="change-details">
                                                <span class="current">
                                                    <?php esc_html_e('Current:', 'ez-translate'); ?>
                                                    <?php echo esc_html($values['current']); ?>
                                                </span>
                                                <span class="arrow">→</span>
                                                <span class="new">
                                                    <?php esc_html_e('New:', 'ez-translate'); ?>
                                                    <?php echo esc_html($values['backup']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Default Metadata Section -->
        <?php if (!empty($comparison['default_metadata']['changes'])): ?>
            <div class="default-metadata-section">
                <h4><?php esc_html_e('Default Language Metadata Changes', 'ez-translate'); ?></h4>
                <label>
                    <input type="checkbox" name="import_default_metadata" value="1" checked>
                    <?php esc_html_e('Update default language metadata', 'ez-translate'); ?>
                </label>
                <div class="changes-preview">
                    <?php foreach ($comparison['default_metadata']['changes'] as $field => $values): ?>
                        <div class="field-change">
                            <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $field))); ?>:</strong>
                            <div class="change-details">
                                <span class="current">
                                    <?php esc_html_e('Current:', 'ez-translate'); ?>
                                    <?php echo esc_html($values['current']); ?>
                                </span>
                                <span class="arrow">→</span>
                                <span class="new">
                                    <?php esc_html_e('New:', 'ez-translate'); ?>
                                    <?php echo esc_html($values['backup']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Import Button -->
        <div class="import-actions">
            <button type="submit" class="button button-primary" name="confirm_import" value="1">
                <?php esc_html_e('Import Selected Changes', 'ez-translate'); ?>
            </button>
            <button type="button" class="button button-secondary cancel-import">
                <?php esc_html_e('Cancel', 'ez-translate'); ?>
            </button>
        </div>

    <?php endif; ?>
</div>