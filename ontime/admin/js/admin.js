/**
 * OnTime Admin JS
 * 
 * JavaScript for admin panel
 * 
 * @package OnTime
 */

jQuery(document).ready(function($) {
    'use strict';

    // Confirmation for delete actions
    $(document).on('click', '.ontime-delete', function(e) {
        if (!confirm(OnTimeAdmin.texts.confirm_delete)) {
            e.preventDefault();
            return false;
        }
        return true;
    });

    // Load Persian Datepicker for all date fields
    function loadPersianDatepicker() {
        // Check if already loaded
        if (typeof $.fn.persianDatepicker !== 'undefined') {
            $('.ontime-datepicker').each(function() {
                var $input = $(this);
                var options = {
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    onSelect: function(unix) {
                        // Format the selected date
                        var date = new persianDate([unix]).format('YYYY/MM/DD');
                        $input.val(date).trigger('change');
                    }
                };
                
                // Add custom placeholder if exists
                if ($input.attr('placeholder')) {
                    options.altField = $input;
                    options.altFormat = 'YYYY/MM/DD';
                }
                
                $input.persianDatepicker(options);
            });
            return;
        }

        // Load Persian Datepicker from CDN
        if (!window.OnTimePersianDatepickerLoaded) {
            window.OnTimePersianDatepickerLoaded = true;
            
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js';
            script.onload = function() {
                loadPersianDatepicker();
            };
            document.head.appendChild(script);

            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdn.jsdelivr.net/npm/persian-datepicker@1.0.0/dist/css/persian-datepicker.min.css';
            document.head.appendChild(link);
        }
    }

    // Load Persian Datepicker
    loadPersianDatepicker();

    // Toggle bulk actions
    $(document).on('click', '#cb-select-all-1, #cb-select-all-2', function() {
        var $selectAll = $(this);
        var $table = $selectAll.closest('table');
        var $checkboxes = $table.find('input[name="appointment_ids[]"]');
        
        $checkboxes.prop('checked', $selectAll.prop('checked'));
    });

    // Bulk action confirmation
    $(document).on('click', '[name="action"]', function() {
        var $select = $(this);
        var action = $select.val();
        
        if (action === 'delete') {
            if (!confirm(OnTimeAdmin.texts.confirm_delete)) {
                $select.val('-1');
                return false;
            }
        }
        
        // Check if any items are selected for bulk action
        if (action !== '-1' && action !== '') {
            var $checked = $('input[name="appointment_ids[]"]:checked');
            if ($checked.length === 0) {
                alert(OnTimeAdmin.texts.no_items_selected);
                $select.val('-1');
                return false;
            }
        }
    });

    // Initialize any admin-specific functionality
    window.OnTimeAdminReady = true;

    // Log for debugging
    if (typeof console !== 'undefined' && typeof console.log !== 'undefined') {
        console.log('OnTime Admin JS loaded');
    }
});
