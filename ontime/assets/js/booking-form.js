/**
 * OnTime Multi-Step Booking Form - Vanilla JavaScript
 * 
 * Pure JavaScript implementation with no dependencies
 * Features:
 * - Step-by-step navigation
 * - Form validation
 * - AJAX communication with WordPress
 * - Jalali date picker
 * - Mobile-first responsive behavior
 * - Minimal footprint for PageSpeed optimization
 * 
 * @version 1.0.0
 * @license GPL-2.0+
 */

// Immediately Invoked Function Expression (IIFE) to avoid polluting global scope
(function() {
    'use strict';

    // ========================================================================
    // CONFIGURATION & STATE
    // ========================================================================

    // Check if OnTimeBooking is defined (from wp_localize_script)
    if (typeof OnTimeBooking === 'undefined') {
        console.warn('OnTimeBooking configuration not found. Make sure wp_localize_script is called.');
        return;
    }

    const config = {
        ajaxurl: OnTimeBooking.ajaxurl || '',
        nonce: OnTimeBooking.nonce || '',
        texts: OnTimeBooking.texts || {},
        config: OnTimeBooking.config || {}
    };

    // State for the current form
    let state = {
        currentStep: 1,
        totalSteps: 5,
        selectedService: null,
        selectedStaff: null,
        selectedDate: null,
        selectedTime: null,
        serviceData: null,
        staffData: null,
        dateData: null
    };

    // DOM elements cache
    let elements = {};

    // ========================================================================
    // UTILITY FUNCTIONS
    // ========================================================================

    /**
     * Query selector helper with caching
     */
    function getElement(selector, container = document) {
        return container.querySelector(selector);
    }

    function getAllElements(selector, container = document) {
        return Array.from(container.querySelectorAll(selector));
    }

    /**
     * Simple debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Format price with commas
     */
    function formatPrice(price) {
        if (!price) return '0';
        return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    /**
     * Format Jalali date for display
     */
    function formatJalaliDate(dateString) {
        if (!dateString) return '';
        
        const months = [
            'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        ];
        
        const days = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
        
        const parts = dateString.split('/');
        if (parts.length !== 3) return dateString;
        
        const year = parseInt(parts[0]);
        const month = parseInt(parts[1]);
        const day = parseInt(parts[2]);
        
        // Calculate day of week (simplified - would need proper Jalali calculation)
        // For now, we'll use the date from the server
        const dayName = days[getJalaliDayOfWeek(year, month, day)] || '';
        const monthName = months[month - 1] || '';
        
        return `${dayName} ${day} ${monthName} ${year}`;
    }

    /**
     * Calculate Jalali day of week (0 = شنبه, 6 = جمعه)
     * This is a simplified calculation
     */
    function getJalaliDayOfWeek(year, month, day) {
        // This would need proper implementation using the calendar engine
        // For now, return a placeholder
        return (day % 7);
    }

    /**
     * Show loading state on a button
     */
    function showButtonLoading(button) {
        button.classList.add('ontime-btn-loading');
        button.disabled = true;
    }

    function hideButtonLoading(button) {
        button.classList.remove('ontime-btn-loading');
        button.disabled = false;
    }

    /**
     * Show error message
     */
    function showError(message, step) {
        const errorEl = getElement(`.ontime-error-message[data-step="${step}"]`, elements.form);
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        }
    }

    /**
     * Clear error message
     */
    function clearError(step) {
        const errorEl = getElement(`.ontime-error-message[data-step="${step}"]`, elements.form);
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.style.display = 'none';
        }
    }

    /**
     * Show field error
     */
    function showFieldError(fieldId, message) {
        const field = getElement(`#${fieldId}`);
        if (!field) return;
        
        const errorEl = getElement('.ontime-field-error', field.parentElement);
        if (errorEl) {
            errorEl.textContent = message;
        }
        
        field.classList.add('ontime-input-error');
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', `${fieldId}-error`);
    }

    /**
     * Clear field error
     */
    function clearFieldError(fieldId) {
        const field = getElement(`#${fieldId}`);
        if (!field) return;
        
        const errorEl = getElement('.ontime-field-error', field.parentElement);
        if (errorEl) {
            errorEl.textContent = '';
        }
        
        field.classList.remove('ontime-input-error');
        field.removeAttribute('aria-invalid');
        field.removeAttribute('aria-describedby');
    }

    /**
     * Validate email
     */
    function isValidEmail(email) {
        if (!email) return true; // Email is optional
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    /**
     * Validate Persian phone number (09XXXXXXXXX)
     */
    function isValidPhone(phone) {
        if (!phone) return false;
        // Remove spaces and dashes
        const cleaned = phone.replace(/[\s-]/g, '');
        return /^09[0-9]{9}$/.test(cleaned);
    }

    // ========================================================================
    // AJAX HELPER
    // ========================================================================

    /**
     * Perform AJAX request
     */
    async function ajaxRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', config.nonce);
        
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        try {
            const response = await fetch(config.ajaxurl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                return { success: true, data: result.data };
            } else {
                return { 
                    success: false, 
                    message: result.data?.message || config.texts.error || 'خطایی رخ داد' 
                };
            }
        } catch (error) {
            console.error('AJAX Error:', error);
            return { 
                success: false, 
                message: config.texts.error || 'خطایی رخ داد' 
            };
        }
    }

    // ========================================================================
    // FORM NAVIGATION
    // ========================================================================

    /**
     * Navigate to a specific step
     */
    function goToStep(step) {
        // Validate step
        step = parseInt(step);
        if (step < 1 || step > state.totalSteps) {
            return;
        }

        // Clear any existing errors
        clearError(state.currentStep);
        
        // Hide current step
        const currentStepEl = getElement(`.ontime-form-step.active`, elements.form);
        if (currentStepEl) {
            currentStepEl.classList.remove('active');
        }

        // Show new step
        const newStepEl = getElement(`.ontime-form-step[data-step="${step}"]`, elements.form);
        if (newStepEl) {
            newStepEl.classList.add('active');
        }

        // Update state
        state.currentStep = step;

        // Update progress indicator
        updateProgress();

        // Update form data display in confirmation step
        if (step === 5) {
            updateConfirmationDisplay();
        }

        // Scroll to top of form
        elements.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /**
     * Update progress indicator
     */
    function updateProgress() {
        const progressSteps = getAllElements('.ontime-progress-step', elements.container);
        const progressFill = getElement('.ontime-progress-fill', elements.container);
        
        progressSteps.forEach((stepEl, index) => {
            const stepNum = index + 1;
            stepEl.classList.remove('active', 'completed');
            
            if (stepNum < state.currentStep) {
                stepEl.classList.add('completed');
            } else if (stepNum === state.currentStep) {
                stepEl.classList.add('active');
            }
        });

        // Update progress bar fill
        if (progressFill) {
            const progressPercent = ((state.currentStep - 1) / (state.totalSteps - 1)) * 100;
            progressFill.style.width = `${progressPercent}%`;
        }
    }

    /**
     * Initialize form navigation
     */
    function initNavigation() {
        // Previous buttons
        const prevButtons = getAllElements('.ontime-prev-btn', elements.form);
        prevButtons.forEach(button => {
            button.addEventListener('click', () => {
                const toStep = parseInt(button.dataset.toStep) || state.currentStep - 1;
                goToStep(toStep);
            });
        });

        // Next buttons
        const nextButtons = getAllElements('.ontime-next-btn', elements.form);
        nextButtons.forEach(button => {
            button.addEventListener('click', () => {
                const toStep = parseInt(button.dataset.toStep) || state.currentStep + 1;
                
                // Validate current step before proceeding
                if (validateStep(state.currentStep)) {
                    goToStep(toStep);
                }
            });
        });
    }

    /**
     * Validate current step
     */
    function validateStep(step) {
        clearError(step);
        
        switch (step) {
            case 1: // Service selection
                if (!state.selectedService) {
                    showError(config.texts.select_service || 'لطفاً یک سرویس انتخاب کنید', 1);
                    return false;
                }
                return true;

            case 2: // Staff selection
                if (!state.selectedStaff) {
                    showError(config.texts.select_staff || 'لطفاً یک پرسنل انتخاب کنید', 2);
                    return false;
                }
                return true;

            case 3: // Date and time selection
                if (!state.selectedDate) {
                    showError(config.texts.select_date || 'لطفاً یک تاریخ انتخاب کنید', 3);
                    return false;
                }
                if (!state.selectedTime) {
                    showError(config.texts.select_time || 'لطفاً یک ساعت انتخاب کنید', 3);
                    return false;
                }
                return true;

            case 4: // Customer info
                return validateCustomerInfo();

            case 5: // Confirmation
                // Check if terms are accepted
                const termsCheckbox = getElement('input[name="accept_terms"]', elements.form);
                if (termsCheckbox && !termsCheckbox.checked) {
                    showError('لطفاً با قوانین و مقررات موافقت کنید', 5);
                    return false;
                }
                return true;

            default:
                return true;
        }
    }

    /**
     * Validate customer information form
     */
    function validateCustomerInfo() {
        let isValid = true;
        
        const nameField = getElement('input[name="customer_name"]', elements.form);
        const phoneField = getElement('input[name="customer_phone"]', elements.form);
        const emailField = getElement('input[name="customer_email"]', elements.form);

        // Clear previous errors
        clearFieldError(`ontime-name-${elements.formId}`);
        clearFieldError(`ontime-phone-${elements.formId}`);
        clearFieldError(`ontime-email-${elements.formId}`);
        clearError(4);

        // Validate name
        if (!nameField || !nameField.value.trim()) {
            showFieldError(`ontime-name-${elements.formId}`, config.texts.enter_name || 'نام الزامی است');
            isValid = false;
        }

        // Validate phone
        if (!phoneField || !isValidPhone(phoneField.value)) {
            showFieldError(`ontime-phone-${elements.formId}`, config.texts.enter_phone || 'تلفن الزامی است');
            isValid = false;
        }

        // Validate email if provided
        if (emailField && emailField.value && !isValidEmail(emailField.value)) {
            showFieldError(`ontime-email-${elements.formId}`, config.texts.invalid_email || 'ایمیل معتبر نیست');
            isValid = false;
        }

        if (!isValid) {
            showError(config.texts.error || 'لطفاً خطاهای را اصلاح کنید', 4);
        }

        return isValid;
    }

    // ========================================================================
    // SERVICE SELECTION
    // ========================================================================

    function initServiceSelection() {
        const serviceCards = getAllElements('.ontime-service-card', elements.form);
        
        serviceCards.forEach(card => {
            card.addEventListener('click', () => {
                // Remove selection from all cards
                serviceCards.forEach(c => c.classList.remove('selected'));
                
                // Add selection to clicked card
                card.classList.add('selected');
                
                // Update state
                state.selectedService = {
                    id: parseInt(card.dataset.serviceId),
                    name: card.dataset.serviceName,
                    price: parseInt(card.dataset.servicePrice),
                    duration: parseInt(card.dataset.serviceDuration)
                };
                
                // Update hidden fields
                updateHiddenFields();
                
                // Clear any error
                clearError(1);
            });
        });

        // Check for preset service
        const presetService = elements.container.dataset.presetService;
        if (presetService) {
            const presetCard = getElement(`.ontime-service-card[data-service-id="${presetService}"]`, elements.form);
            if (presetCard) {
                presetCard.click();
            }
        }
    }

    // ========================================================================
    // STAFF SELECTION
    // ========================================================================

    function initStaffSelection() {
        const staffCards = getAllElements('.ontime-staff-card', elements.form);
        
        staffCards.forEach(card => {
            card.addEventListener('click', () => {
                // Remove selection from all cards
                staffCards.forEach(c => c.classList.remove('selected'));
                
                // Add selection to clicked card
                card.classList.add('selected');
                
                // Update state
                state.selectedStaff = {
                    id: parseInt(card.dataset.staffId),
                    name: card.dataset.staffName
                };
                
                // Update hidden fields
                updateHiddenFields();
                
                // Clear any error
                clearError(2);
                
                // Could load available dates here if needed
            });
        });

        // Check for preset staff
        const presetStaff = elements.container.dataset.presetStaff;
        if (presetStaff) {
            const presetCard = getElement(`.ontime-staff-card[data-staff-id="${presetStaff}"]`, elements.form);
            if (presetCard) {
                presetCard.click();
            }
        }
    }

    // ==========================================================================
    // JALALI DATEPICKER
    // ========================================================================

    /**
     * Initialize Jalali date picker
     */
    function initJalaliDatepicker() {
        const dateInput = getElement(`#ontime-date-${elements.formId}`, elements.form);
        const calendarContainer = getElement(`#ontime-calendar-${elements.formId}`, elements.form);
        
        if (!dateInput) return;

        // Parse current date from input value
        let currentDate = dateInput.value || state.dateData.currentDate || getCurrentJalaliDate();
        
        // Toggle calendar on input click
        dateInput.addEventListener('click', (e) => {
            e.stopPropagation();
            if (calendarContainer) {
                calendarContainer.classList.toggle('active');
            }
        });

        // Close calendar when clicking outside
        document.addEventListener('click', (e) => {
            if (calendarContainer && calendarContainer.classList.contains('active')) {
                if (!calendarContainer.contains(e.target) && e.target !== dateInput) {
                    calendarContainer.classList.remove('active');
                }
            }
        });

        // Generate calendar
        if (calendarContainer) {
            renderCalendar(calendarContainer, currentDate, dateInput);
        }

        // Update selected date when input changes
        dateInput.addEventListener('change', () => {
            const date = dateInput.value;
            if (isValidJalaliDate(date)) {
                state.selectedDate = date;
                updateHiddenFields();
                clearError(3);
                
                // Load available slots for this date
                if (state.selectedService && state.selectedStaff) {
                    loadAvailableSlots(date);
                }
            }
        });

        // If date is already set, load slots
        if (state.selectedDate && state.selectedService && state.selectedStaff) {
            loadAvailableSlots(state.selectedDate);
        }
    }

    /**
     * Get current Jalali date (simplified)
     */
    function getCurrentJalaliDate() {
        // This would need proper implementation
        // For now, use the date from server if available
        const formDataEl = getElement(`#ontime-form-data-${elements.formId}`);
        if (formDataEl) {
            try {
                const data = JSON.parse(formDataEl.textContent);
                return data.currentDate || '1403/01/01';
            } catch (e) {
                return '1403/01/01';
            }
        }
        return '1403/01/01';
    }

    /**
     * Check if Jalali date is valid
     */
    function isValidJalaliDate(date) {
        if (!date) return false;
        const parts = date.split('/');
        if (parts.length !== 3) return false;
        
        const year = parseInt(parts[0]);
        const month = parseInt(parts[1]);
        const day = parseInt(parts[2]);
        
        if (year < 1300 || month < 1 || month > 12 || day < 1) return false;
        
        const daysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        if (day > daysInMonth[month - 1]) return false;
        
        return true;
    }

    /**
     * Render Jalali calendar
     */
    function renderCalendar(container, date, input) {
        // Parse date
        const parts = date.split('/');
        let year = parseInt(parts[0]);
        let month = parseInt(parts[1]);
        
        // Month names
        const monthNames = [
            'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        ];
        
        // Days of week
        const dayNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
        
        // Clear container
        container.innerHTML = '';

        // Calendar header
        const header = document.createElement('div');
        header.className = 'ontime-calendar-header';
        
        // Month navigation
        const nav = document.createElement('div');
        nav.className = 'ontime-calendar-nav';
        
        const prevMonth = document.createElement('button');
        prevMonth.className = 'ontime-calendar-btn ontime-calendar-prev';
        prevMonth.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>';
        prevMonth.addEventListener('click', () => {
            month--;
            if (month < 1) {
                month = 12;
                year--;
            }
            renderCalendar(container, `${year}/${month.toString().padStart(2, '0')}/01`, input);
        });
        
        const nextMonth = document.createElement('button');
        nextMonth.className = 'ontime-calendar-btn ontime-calendar-next';
        nextMonth.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>';
        nextMonth.addEventListener('click', () => {
            month++;
            if (month > 12) {
                month = 1;
                year++;
            }
            renderCalendar(container, `${year}/${month.toString().padStart(2, '0')}/01`, input);
        });
        
        nav.appendChild(prevMonth);
        nav.appendChild(nextMonth);
        header.appendChild(nav);
        
        // Month/year title
        const title = document.createElement('div');
        title.className = 'ontime-calendar-title';
        title.textContent = `${monthNames[month - 1]} ${year}`;
        header.appendChild(title);
        
        container.appendChild(header);

        // Day names row
        const dayNamesRow = document.createElement('div');
        dayNamesRow.className = 'ontime-calendar-day-names';
        
        dayNames.forEach(day => {
            const dayEl = document.createElement('div');
            dayEl.className = 'ontime-calendar-day-name';
            dayEl.textContent = day;
            dayNamesRow.appendChild(dayEl);
        });
        
        container.appendChild(dayNamesRow);

        // Days grid
        const daysGrid = document.createElement('div');
        daysGrid.className = 'ontime-calendar-days';
        
        // Get first day of month and total days
        const firstDayOfWeek = getFirstDayOfMonth(year, month);
        const daysInMonth = getDaysInMonth(year, month);
        
        // Add empty cells for days before first day of month
        for (let i = 0; i < firstDayOfWeek; i++) {
            const emptyCell = document.createElement('button');
            emptyCell.className = 'ontime-calendar-day ontime-calendar-day-empty';
            emptyCell.disabled = true;
            daysGrid.appendChild(emptyCell);
        }
        
        // Add days of month
        for (let day = 1; day <= daysInMonth; day++) {
            const dayEl = document.createElement('button');
            dayEl.className = 'ontime-calendar-day';
            dayEl.textContent = day;
            dayEl.dataset.date = `${year}/${month.toString().padStart(2, '0')}/${day.toString().padStart(2, '0')}`;
            
            // Check if date is in the past
            const dateStr = dayEl.dataset.date;
            if (isPastDate(dateStr)) {
                dayEl.classList.add('ontime-calendar-day-past');
                dayEl.disabled = true;
            }
            
            // Check if date is selected
            if (state.selectedDate === dateStr) {
                dayEl.classList.add('selected');
            }
            
            // Check if date is today
            if (dateStr === state.dateData.currentDate) {
                dayEl.classList.add('ontime-calendar-day-today');
            }
            
            dayEl.addEventListener('click', () => {
                // Select this date
                const selectedDate = dayEl.dataset.date;
                
                // Update input
                input.value = selectedDate;
                
                // Update state
                state.selectedDate = selectedDate;
                
                // Update hidden fields
                updateHiddenFields();
                
                // Remove selection from all days
                getAllElements('.ontime-calendar-day', container).forEach(d => {
                    d.classList.remove('selected');
                });
                
                // Add selection to clicked day
                dayEl.classList.add('selected');
                
                // Close calendar
                container.classList.remove('active');
                
                // Load available slots
                if (state.selectedService && state.selectedStaff) {
                    loadAvailableSlots(selectedDate);
                }
                
                // Clear any error
                clearError(3);
            });
            
            daysGrid.appendChild(dayEl);
        }
        
        container.appendChild(daysGrid);

        // Add some basic styles for calendar (inline for minimal CSS)
        if (!container.querySelector('style')) {
            const style = document.createElement('style');
            style.textContent = `
                .ontime-calendar-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1rem;
                }
                .ontime-calendar-nav {
                    display: flex;
                    gap: 0.5rem;
                }
                .ontime-calendar-btn {
                    width: 32px;
                    height: 32px;
                    border: none;
                    background: transparent;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 0.5rem;
                    color: #6b7280;
                }
                .ontime-calendar-btn:hover {
                    background: #f3f4f6;
                    color: #111827;
                }
                .ontime-calendar-title {
                    font-weight: 600;
                    color: #111827;
                }
                .ontime-calendar-day-names {
                    display: grid;
                    grid-template-columns: repeat(7, 1fr);
                    gap: 0.25rem;
                    margin-bottom: 0.5rem;
                }
                .ontime-calendar-day-name {
                    text-align: center;
                    font-size: 0.75rem;
                    font-weight: 500;
                    color: #6b7280;
                    padding: 0.5rem 0;
                }
                .ontime-calendar-days {
                    display: grid;
                    grid-template-columns: repeat(7, 1fr);
                    gap: 0.25rem;
                }
                .ontime-calendar-day {
                    aspect-ratio: 1;
                    border: 1px solid #e5e7eb;
                    background: white;
                    cursor: pointer;
                    font-size: 0.875rem;
                    border-radius: 0.5rem;
                    transition: all 0.2s;
                }
                .ontime-calendar-day:hover:not(:disabled) {
                    border-color: #2563eb;
                    color: #2563eb;
                }
                .ontime-calendar-day.selected {
                    background: #2563eb;
                    color: white;
                    border-color: #2563eb;
                }
                .ontime-calendar-day-today {
                    border-color: #2563eb;
                    font-weight: 600;
                }
                .ontime-calendar-day-past {
                    color: #9ca3af;
                    cursor: not-allowed;
                }
                .ontime-calendar-day-empty {
                    background: transparent;
                    border: none;
                    cursor: default;
                }
                .ontime-calendar-day:disabled {
                    cursor: not-allowed;
                    opacity: 0.5;
                }
            `;
            container.appendChild(style);
        }
    }

    /**
     * Get first day of month (0 = شنبه, 6 = جمعه)
     */
    function getFirstDayOfMonth(year, month) {
        // This is a simplified calculation
        // For proper Jalali calendar, use the server-side calculation
        // Here we use a lookup table for demonstration
        return Math.floor(Math.random() * 7); // Placeholder
    }

    /**
     * Get days in month
     */
    function getDaysInMonth(year, month) {
        const daysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        
        // Check for leap year (Esfand has 30 days in leap year)
        if (month === 12 && isJalaliLeapYear(year)) {
            return 30;
        }
        
        return daysInMonth[month - 1] || 30;
    }

    /**
     * Check if Jalali year is leap year
     */
    function isJalaliLeapYear(year) {
        return ((year % 33) % 4) === 1;
    }

    /**
     * Check if date is in the past
     */
    function isPastDate(date) {
        if (!state.dateData.currentDate) return false;
        return date < state.dateData.currentDate;
    }

    // ========================================================================
    // TIME SLOTS
    // ========================================================================

    /**
     * Load available time slots for a date
     */
    async function loadAvailableSlots(date) {
        if (!state.selectedService || !state.selectedStaff || !date) {
            return;
        }

        const slotsContainer = getElement(`#ontime-slots-grid-${elements.formId}`, elements.form);
        if (!slotsContainer) return;

        // Show loading
        slotsContainer.innerHTML = `
            <div class="ontime-slots-loading">
                <div class="ontime-spinner"></div>
                <p>${config.texts.loading || 'در حال بارگذاری...'}</p>
            </div>
        `;

        try {
            const result = await ajaxRequest('ontime_get_available_slots', {
                service_id: state.selectedService.id,
                staff_id: state.selectedStaff.id,
                date: date
            });

            if (result.success) {
                renderSlots(result.data.slots);
            } else {
                slotsContainer.innerHTML = `
                    <div class="ontime-slots-error">
                        <p>${result.message || config.texts.no_slots || 'ساعت آزاد یافت نشد'}</p>
                    </div>
                `;
            }
        } catch (error) {
            slotsContainer.innerHTML = `
                <div class="ontime-slots-error">
                    <p>${config.texts.error || 'خطایی رخ داد'}</p>
                </div>
            `;
        }
    }

    /**
     * Render time slots
     */
    function renderSlots(slots) {
        const slotsContainer = getElement(`#ontime-slots-grid-${elements.formId}`, elements.form);
        if (!slotsContainer) return;

        // Clear container
        slotsContainer.innerHTML = '';

        if (!slots || slots.length === 0) {
            slotsContainer.innerHTML = `
                <div class="ontime-slots-empty">
                    <p>${config.texts.no_slots || 'در این تاریخ ساعت آزاد وجود ندارد'}</p>
                </div>
            `;
            return;
        }

        // Create slot buttons
        slots.forEach(slot => {
            const slotBtn = document.createElement('button');
            slotBtn.className = 'ontime-slot-btn';
            slotBtn.textContent = slot.formatted;
            slotBtn.dataset.time = slot.time;
            slotBtn.dataset.formatted = slot.formatted;
            
            slotBtn.addEventListener('click', () => {
                // Remove selection from all slots
                getAllElements('.ontime-slot-btn', slotsContainer).forEach(btn => {
                    btn.classList.remove('selected');
                });
                
                // Add selection to clicked slot
                slotBtn.classList.add('selected');
                
                // Update state
                state.selectedTime = slot.time;
                
                // Update hidden fields
                updateHiddenFields();
                
                // Clear any error
                clearError(3);
            });

            slotsContainer.appendChild(slotBtn);
        });
    }

    // ========================================================================
    // CONFIRMATION
    // ========================================================================

    /**
     * Update confirmation display with selected data
     */
    function updateConfirmationDisplay() {
        // Service
        const confirmService = getElement(`#ontime-confirm-service-${elements.formId}`);
        if (confirmService && state.selectedService) {
            confirmService.textContent = `${state.selectedService.name} (${state.selectedService.duration} دقیقه)`;
        }

        // Staff
        const confirmStaff = getElement(`#ontime-confirm-staff-${elements.formId}`);
        if (confirmStaff && state.selectedStaff) {
            confirmStaff.textContent = state.selectedStaff.name;
        }

        // Date and time
        const confirmDateTime = getElement(`#ontime-confirm-datetime-${elements.formId}`);
        if (confirmDateTime && state.selectedDate && state.selectedTime) {
            confirmDateTime.textContent = `${formatJalaliDate(state.selectedDate)} - ساعت ${state.selectedTime}`;
        }

        // Duration
        const confirmDuration = getElement(`#ontime-confirm-duration-${elements.formId}`);
        if (confirmDuration && state.selectedService) {
            confirmDuration.textContent = `${state.selectedService.duration} دقیقه`;
        }

        // Price
        const confirmPrice = getElement(`#ontime-confirm-price-${elements.formId}`);
        if (confirmPrice && state.selectedService) {
            confirmPrice.textContent = `${formatPrice(state.selectedService.price)} تومان`;
        }

        // Customer info
        const nameField = getElement(`#ontime-name-${elements.formId}`);
        const phoneField = getElement(`#ontime-phone-${elements.formId}`);
        const emailField = getElement(`#ontime-email-${elements.formId}`);
        const notesField = getElement(`#ontime-notes-${elements.formId}`);

        if (nameField) {
            const confirmName = getElement(`#ontime-confirm-name-${elements.formId}`);
            if (confirmName) {
                confirmName.textContent = nameField.value || '—';
            }
        }

        if (phoneField) {
            const confirmPhone = getElement(`#ontime-confirm-phone-${elements.formId}`);
            if (confirmPhone) {
                confirmPhone.textContent = phoneField.value || '—';
            }
        }

        if (emailField) {
            const confirmEmail = getElement(`#ontime-confirm-email-${elements.formId}`);
            if (confirmEmail) {
                confirmEmail.textContent = emailField.value || '—';
            }
        }

        if (notesField) {
            const confirmNotes = getElement(`#ontime-confirm-notes-${elements.formId}`);
            if (confirmNotes) {
                confirmNotes.textContent = notesField.value || '—';
            }
        }
    }

    // ========================================================================
    // FORM SUBMISSION
    // ========================================================================

    /**
     * Handle form submission
     */
    async function handleFormSubmit(e) {
        e.preventDefault();

        // Validate all steps
        for (let step = 1; step <= state.totalSteps; step++) {
            if (!validateStep(step)) {
                goToStep(step);
                return;
            }
        }

        // Get form data
        const formData = new FormData(e.target);
        const submitBtn = getElement('.ontime-confirm-btn', elements.form);
        
        // Show loading on submit button
        if (submitBtn) {
            showButtonLoading(submitBtn);
        }

        try {
            // Prepare submission data
            const submitData = {
                service_id: state.selectedService.id,
                staff_id: state.selectedStaff.id,
                appointment_date: state.selectedDate,
                appointment_time: state.selectedTime,
                service_price: state.selectedService.price,
                service_duration: state.selectedService.duration,
                customer_name: formData.get('customer_name'),
                customer_phone: formData.get('customer_phone'),
                customer_email: formData.get('customer_email'),
                customer_notes: formData.get('customer_notes'),
                accept_terms: formData.get('accept_terms') ? true : false
            };

            // Submit via AJAX
            const result = await ajaxRequest('ontime_confirm_booking', submitData);

            if (result.success) {
                // Show success modal
                showSuccessModal(result.data);
                
                // Reset form (optional)
                // e.target.reset();
                
                // Reset state
                state.currentStep = 1;
                
                // Go back to first step
                goToStep(1);
                
                // Update progress
                updateProgress();
                
                // Clear selections
                state.selectedService = null;
                state.selectedStaff = null;
                state.selectedDate = null;
                state.selectedTime = null;
                
                // Clear hidden fields
                updateHiddenFields();
                
                // Clear service and staff selections
                getAllElements('.ontime-service-card', elements.form).forEach(card => {
                    card.classList.remove('selected');
                });
                getAllElements('.ontime-staff-card', elements.form).forEach(card => {
                    card.classList.remove('selected');
                });

            } else {
                // Show error at current step
                showError(result.message || config.texts.error || 'خطایی رخ داد', state.currentStep);
            }
        } catch (error) {
            showError(config.texts.error || 'خطایی رخ داد', state.currentStep);
        } finally {
            if (submitBtn) {
                hideButtonLoading(submitBtn);
            }
        }
    }

    /**
     * Show success modal
     */
    function showSuccessModal(data) {
        const modal = getElement(`#ontime-success-modal-${elements.formId}`);
        const successMsg = getElement(`#ontime-success-msg-${elements.formId}`);
        const successSummary = getElement(`#ontime-success-summary-${elements.formId}`);
        
        if (!modal) return;

        // Set success message
        if (successMsg) {
            successMsg.textContent = config.texts.success || 'نوبت شما با موفقیت رزرو شد!';
        }

        // Set summary
        if (successSummary && data.appointment) {
            const app = data.appointment;
            successSummary.innerHTML = `
                <p><strong>شماره نوبت:</strong> ${app.id}</p>
                <p><strong>سرویس:</strong> ${app.service_name}</p>
                <p><strong>پرسنل:</strong> ${app.staff_name}</p>
                <p><strong>تاریخ:</strong> ${formatJalaliDate(app.date)}</p>
                <p><strong>ساعت:</strong> ${app.time}</p>
                <p><strong>مدت:</strong> ${app.duration} دقیقه</p>
                <p><strong>قیمت:</strong> ${formatPrice(app.price)} تومان</p>
            `;
        }

        // Show modal
        modal.classList.add('active');
        
        // Close modal on overlay click or button click
        const overlay = getElement('.ontime-modal-overlay', modal);
        const closeBtn = getElement('.ontime-modal-close', modal);
        
        const closeModal = () => {
            modal.classList.remove('active');
        };
        
        if (overlay) {
            overlay.addEventListener('click', closeModal);
        }
        
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
    }

    // ========================================================================
    // HIDDEN FIELDS
    // ========================================================================

    /**
     * Update hidden form fields with current selections
     */
    function updateHiddenFields() {
        const serviceField = getElement(`#ontime-hidden-service-${elements.formId}`);
        const staffField = getElement(`#ontime-hidden-staff-${elements.formId}`);
        const dateField = getElement(`#ontime-hidden-date-${elements.formId}`);
        const timeField = getElement(`#ontime-hidden-time-${elements.formId}`);
        const priceField = getElement(`#ontime-hidden-price-${elements.formId}`);
        const durationField = getElement(`#ontime-hidden-duration-${elements.formId}`);

        if (serviceField) {
            serviceField.value = state.selectedService ? state.selectedService.id : '';
        }
        
        if (staffField) {
            staffField.value = state.selectedStaff ? state.selectedStaff.id : '';
        }
        
        if (dateField) {
            dateField.value = state.selectedDate || '';
        }
        
        if (timeField) {
            timeField.value = state.selectedTime || '';
        }
        
        if (priceField) {
            priceField.value = state.selectedService ? state.selectedService.price : '';
        }
        
        if (durationField) {
            durationField.value = state.selectedService ? state.selectedService.duration : '';
        }
    }

    // ========================================================================
    // INITIALIZATION
    // ========================================================================

    /**
     * Initialize a single booking form
     */
    function initForm(container) {
        const formId = container.dataset.formId;
        
        // Get form element
        const form = getElement(`#ontime-booking-form-${formId}`, container);
        if (!form) return;

        // Parse inline data
        const dataEl = getElement(`#ontime-form-data-${formId}`, container);
        if (dataEl) {
            try {
                state.dateData = JSON.parse(dataEl.textContent);
                
                // Set initial date if available
                if (state.dateData.currentDate) {
                    const dateInput = getElement(`#ontime-date-${formId}`, form);
                    if (dateInput && !dateInput.value) {
                        dateInput.value = state.dateData.currentDate;
                    }
                }
            } catch (e) {
                console.error('Error parsing form data:', e);
            }
        }

        // Store elements
        elements = {
            container: container,
            form: form,
            formId: formId
        };

        // Initialize form
        initNavigation();
        initServiceSelection();
        initStaffSelection();
        initJalaliDatepicker();
        
        // Form submission
        form.addEventListener('submit', handleFormSubmit);

        // Input field validation on blur
        const nameField = getElement(`#ontime-name-${formId}`, form);
        const phoneField = getElement(`#ontime-phone-${formId}`, form);
        const emailField = getElement(`#ontime-email-${formId}`, form);

        if (nameField) {
            nameField.addEventListener('blur', () => {
                if (!nameField.value.trim()) {
                    showFieldError(`ontime-name-${formId}`, config.texts.enter_name || 'نام الزامی است');
                } else {
                    clearFieldError(`ontime-name-${formId}`);
                }
            });
            
            nameField.addEventListener('input', () => {
                if (nameField.value.trim()) {
                    clearFieldError(`ontime-name-${formId}`);
                }
            });
        }

        if (phoneField) {
            phoneField.addEventListener('blur', () => {
                if (!isValidPhone(phoneField.value)) {
                    showFieldError(`ontime-phone-${formId}`, config.texts.enter_phone || 'تلفن الزامی است');
                } else {
                    clearFieldError(`ontime-phone-${formId}`);
                }
            });
            
            phoneField.addEventListener('input', () => {
                if (isValidPhone(phoneField.value)) {
                    clearFieldError(`ontime-phone-${formId}`);
                }
            });
        }

        if (emailField) {
            emailField.addEventListener('blur', () => {
                if (emailField.value && !isValidEmail(emailField.value)) {
                    showFieldError(`ontime-email-${formId}`, config.texts.invalid_email || 'ایمیل معتبر نیست');
                } else {
                    clearFieldError(`ontime-email-${formId}`);
                }
            });
            
            emailField.addEventListener('input', () => {
                if (isValidEmail(emailField.value)) {
                    clearFieldError(`ontime-email-${formId}`);
                }
            });
        }

        // Update hidden fields initially
        updateHiddenFields();

        // Add input styling for focus
        addInputFocusStyles();
    }

    /**
     * Add focus styles for inputs
     */
    function addInputFocusStyles() {
        const inputs = getAllElements('.ontime-input', elements.form);
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                input.classList.add('ontime-input-focus');
            });
            
            input.addEventListener('blur', () => {
                input.classList.remove('ontime-input-focus');
            });
        });
    }

    // ========================================================================
    // PUBLIC API
    // ========================================================================

    /**
     * Initialize all booking forms on the page
     */
    function initAllForms() {
        const containers = getAllElements('.ontime-booking-container');
        containers.forEach(initForm);
    }

    // ========================================================================
    // DOM READY
    // ========================================================================

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllForms);
    } else {
        initAllForms();
    }

    // Also handle dynamic content (e.g., AJAX loaded content)
    // This can be extended with MutationObserver if needed

    // Expose initForm for manual initialization
    window.OnTimeBookingForm = window.OnTimeBookingForm || {};
    window.OnTimeBookingForm.init = initForm;
    window.OnTimeBookingForm.initAll = initAllForms;

})();
