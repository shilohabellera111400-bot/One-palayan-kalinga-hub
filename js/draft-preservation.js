/**
 * draft-preservation.js - LocalStorage Form Auto-Save & Restore Helper
 * Prevents loss of typed responses in resident application forms if page reloads or connection drops.
 */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const targetForms = document.querySelectorAll('form[data-autosave="true"], #aicsForm, #verifyForm, #housingForm, #appointmentForm');
        
        targetForms.forEach((form, index) => {
            const formId = form.id || 'autosave_form_' + index;
            const storageKey = 'form_draft_' + formId;

            // 1. Restore draft values if available
            try {
                const savedData = localStorage.getItem(storageKey);
                if (savedData) {
                    const parsed = JSON.parse(savedData);
                    Object.keys(parsed).forEach(fieldName => {
                        const field = form.elements[fieldName];
                        if (field && field.type !== 'file' && field.type !== 'password' && field.name !== 'csrf_token') {
                            if (field.type === 'checkbox' || field.type === 'radio') {
                                field.checked = parsed[fieldName];
                            } else {
                                field.value = parsed[fieldName];
                            }
                        }
                    });
                }
            } catch (e) {
                console.warn('Draft restoration warning:', e);
            }

            // 2. Listen for inputs and save draft
            form.addEventListener('input', function(e) {
                if (e.target.type === 'file' || e.target.type === 'password' || e.target.name === 'csrf_token') return;
                
                const draft = {};
                Array.from(form.elements).forEach(elem => {
                    if (elem.name && elem.type !== 'file' && elem.type !== 'password' && elem.name !== 'csrf_token') {
                        if (elem.type === 'checkbox' || elem.type === 'radio') {
                            if (elem.checked) draft[elem.name] = elem.value;
                        } else if (elem.value.trim() !== '') {
                            draft[elem.name] = elem.value;
                        }
                    }
                });
                
                localStorage.setItem(storageKey, JSON.stringify(draft));
            });

            // 3. Clear draft on successful form submit
            form.addEventListener('submit', function() {
                localStorage.removeItem(storageKey);
            });
        });
    });
})();
