/**
 * OnTime Settings JS
 * 
 * JavaScript for settings page
 * 
 * @package OnTime
 */

jQuery(document).ready(function($) {
    'use strict';

    // Conditional fields handling
    function toggleConditionalFields() {
        $('[data-conditional]').each(function() {
            var $field = $(this);
            var conditionalOn = $field.data('conditional');
            var $checkbox = $('#' + conditionalOn);
            
            if ($checkbox.length) {
                $field.toggleClass('show', $checkbox.is(':checked'));
            }
        });
    }

    // Initial toggle
    toggleConditionalFields();

    // Toggle on checkbox change
    $('input[type="checkbox"]').on('change', function() {
        var $checkbox = $(this);
        var fieldName = $checkbox.attr('id');
        
        // Toggle fields that depend on this checkbox
        $('[data-conditional="' + fieldName + '"]').each(function() {
            var $field = $(this);
            $field.toggleClass('show', $checkbox.is(':checked'));
        });
    });

    // Confirm before leaving with unsaved changes
    var formChanged = false;
    
    $('form.ontime-settings-form input, form.ontime-settings-form select, form.ontime-settings-form textarea').on('change', function() {
        formChanged = true;
    });

    $(window).on('beforeunload', function() {
        if (formChanged) {
            return OnTimeAdmin.texts.confirm_delete;
        }
    });

    // Mark form as saved after submission
    $('form.ontime-settings-form').on('submit', function() {
        formChanged = false;
    });
});
