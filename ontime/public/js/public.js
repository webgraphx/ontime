/**
 * OnTime Public JS
 * 
 * JavaScript for front-end booking form
 * 
 * @package OnTime
 */

jQuery(document).ready(function($) {
    'use strict';

    const OnTimeBooking = {
        // Initialize booking form
        init: function() {
            this.bindEvents();
            this.loadPersianDatepicker();
        },

        // Bind form events
        bindEvents: function() {
            const $form = $('#ontime-booking-form');
            const $serviceSelect = $('#ontime_service_id');
            const $staffSelect = $('#ontime_staff_id');
            const $dateInput = $('#ontime_date');

            // Load slots when service, staff or date changes
            $serviceSelect.add($staffSelect).add($dateInput).on('change input', function() {
                OnTimeBooking.loadSlots();
            });

            // Form submission
            $form.on('submit', function(e) {
                e.preventDefault();
                OnTimeBooking.submitForm();
            });
        },

        // Load Persian Datepicker
        loadPersianDatepicker: function() {
            // Check if Persian Datepicker is loaded via CDN
            if (typeof $.fn.persianDatepicker !== 'undefined') {
                $('#ontime_date').persianDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    initialValue: false,
                    onSelect: function() {
                        OnTimeBooking.loadSlots();
                    }
                });
            } else {
                // Fallback: Use HTML5 date picker
                $('#ontime_date').attr('type', 'date');
            }
        },

        // Load available time slots via AJAX
        loadSlots: function() {
            const $serviceId = $('#ontime_service_id').val();
            const $staffId = $('#ontime_staff_id').val();
            const $date = $('#ontime_date').val();

            if (!$serviceId || !$staffId || !$date) {
                $('#ontime_slots_container').html('<p>' + OnTimePublic.texts.loading + '</p>');
                return;
            }

            $('#ontime_slots_container').html('<p><em>' + OnTimePublic.texts.loading + '</em></p>');

            $.ajax({
                url: OnTimePublic.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ontime_get_available_slots',
                    service_id: $serviceId,
                    staff_id: $staffId,
                    date: $date,
                    nonce: OnTimePublic.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.slots) {
                        OnTimeBooking.renderSlots(response.data.slots);
                    } else {
                        $('#ontime_slots_container').html('<p style="color:red;">' + (response.data.message || OnTimePublic.texts.error) + '</p>');
                    }
                },
                error: function() {
                    $('#ontime_slots_container').html('<p style="color:red;">' + OnTimePublic.texts.error + '</p>');
                }
            });
        },

        // Render time slots
        renderSlots: function(slots) {
            if (!slots || slots.length === 0) {
                $('#ontime_slots_container').html('<p>' + OnTimePublic.texts.error + '</p>');
                return;
            }

            let html = '<div class="ontime-slots">';
            
            $.each(slots, function(index, slot) {
                html += '<span class="ontime-slot" data-slot="' + slot.start + '">' + slot.display + '</span>';
            });
            
            html += '</div>';
            
            $('#ontime_slots_container').html(html);

            // Bind click event to slots
            $('.ontime-slot').on('click', function() {
                $('.ontime-slot').removeClass('selected');
                $(this).addClass('selected');
            });
        },

        // Submit booking form
        submitForm: function() {
            const $form = $('#ontime-booking-form');
            const $selectedSlot = $('.ontime-slot.selected');

            if (!$selectedSlot.length) {
                alert('لطفا یک ساعت آزاد انتخاب کنید');
                return false;
            }

            const $button = $form.find('button[type="submit"]');
            const originalText = $button.text();
            
            $button.prop('disabled', true).text(OnTimePublic.texts.loading);

            // Collect form data
            const formData = {
                action: 'ontime_create_appointment',
                nonce: OnTimePublic.nonce,
                customer_name: $('#ontime_customer_name').val(),
                customer_phone: $('#ontime_customer_phone').val(),
                customer_email: $('#ontime_customer_email').val(),
                service_id: $('#ontime_service_id').val(),
                staff_id: $('#ontime_staff_id').val(),
                date: $('#ontime_date').val(),
                slot: $selectedSlot.data('slot')
            };

            $.ajax({
                url: OnTimePublic.ajaxurl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || OnTimePublic.texts.success);
                        $form.trigger('reset');
                        $('.ontime-slot').removeClass('selected');
                    } else {
                        alert(response.data.message || OnTimePublic.texts.error);
                    }
                },
                error: function() {
                    alert(OnTimePublic.texts.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }
    };

    // Initialize booking
    OnTimeBooking.init();
});
