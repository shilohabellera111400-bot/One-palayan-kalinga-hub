/**
 * AICS Multi-Step Form Wizard
 * Palayan-Kalinga Hub - CSWDO Palayan City
 * Provides smooth, professional step transitions, validation, and a summary preview page.
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('applicationForm');
    if (!form) return;

    const steps = Array.from(document.querySelectorAll('.form-step'));
    const stepContents = Array.from(document.querySelectorAll('.wizard-step'));
    let currentStep = 1;

    // Create Navigation Buttons for each step (except the last where the native submit lives)
    stepContents.forEach((stepContent, index) => {
        const stepNum = index + 1;
        const btnContainer = document.createElement('div');
        btnContainer.className = 'wizard-buttons';

        // Add Back button if not on Step 1
        if (stepNum > 1) {
            const backBtn = document.createElement('button');
            backBtn.type = 'button';
            backBtn.className = 'btn-wizard btn-wizard-back';
            backBtn.innerHTML = '<i class="fas fa-arrow-left"></i> Back';
            backBtn.addEventListener('click', () => goToStep(stepNum - 1));
            btnContainer.appendChild(backBtn);
        }

        // Add Next button if not on the final step (Step 3)
        if (stepNum < 3) {
            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'btn-wizard btn-wizard-next';
            nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right"></i>';
            nextBtn.addEventListener('click', () => {
                if (validateStep(stepNum)) {
                    goToStep(stepNum + 1);
                }
            });
            btnContainer.appendChild(nextBtn);
        }

        stepContent.appendChild(btnContainer);
    });

    // Hide all step sections except step 1
    goToStep(1);

    function goToStep(targetStep) {
        if (targetStep < 1 || targetStep > 3) return;

        // Save current step reference
        currentStep = targetStep;

        // Toggle active wizard contents
        stepContents.forEach(content => {
            const stepNum = parseInt(content.getAttribute('data-step'));
            if (stepNum === targetStep) {
                content.style.display = 'block';
                content.classList.add('fade-in-active');
            } else {
                content.style.display = 'none';
                content.classList.remove('fade-in-active');
            }
        });

        // Toggle step status classes
        steps.forEach((step, index) => {
            const stepNum = index + 1;
            step.classList.remove('active', 'done');
            if (stepNum === targetStep) {
                step.classList.add('active');
            } else if (stepNum < targetStep) {
                step.classList.add('done');
            }
        });

        // If going to step 3, build the summary preview table
        if (targetStep === 3) {
            buildSummary();
        }

        // Smooth scroll to top of form panel
        const formPanel = document.querySelector('.form-panel');
        if (formPanel) {
            formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function validateStep(stepNum) {
        const currentContainer = document.querySelector(`.wizard-step[data-step="${stepNum}"]`);
        if (!currentContainer) return true;

        const inputs = currentContainer.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;

        inputs.forEach(input => {
            let value = input.value.trim();
            let parent = input.closest('.form-group') || input.closest('.upload-zone');
            
            // Check file inputs specifically
            if (input.type === 'file') {
                if (!input.files || input.files.length === 0) {
                    isValid = false;
                    parent.classList.add('validation-error');
                    showError(input, 'Please upload this required document');
                } else {
                    parent.classList.remove('validation-error');
                    removeError(input);
                }
            } else {
                // Text, select, number, textarea validation
                if (!value) {
                    isValid = false;
                    input.classList.add('validation-error');
                    showError(input, 'This field is required');
                } else {
                    input.classList.remove('validation-error');
                    removeError(input);
                }
            }
        });

        return isValid;
    }

    function showError(input, msg) {
        let parent = input.closest('.form-group') || input.closest('.upload-zone');
        if (!parent) return;
        
        let errorMsg = parent.querySelector('.wizard-error-message');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'wizard-error-message';
            parent.appendChild(errorMsg);
        }
        errorMsg.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + msg;
    }

    function removeError(input) {
        let parent = input.closest('.form-group') || input.closest('.upload-zone');
        if (!parent) return;
        let errorMsg = parent.querySelector('.wizard-error-message');
        if (errorMsg) errorMsg.remove();
    }

    function buildSummary() {
        const summaryContainer = document.getElementById('summary-preview-table');
        if (!summaryContainer) return;

        summaryContainer.innerHTML = ''; // Clear previous

        const summaryList = document.createElement('div');
        summaryList.className = 'summary-list';

        // 1. Collect form inputs (excluding token, file inputs)
        const inputs = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]), select, textarea');
        
        inputs.forEach(input => {
            const labelEl = input.closest('.form-group')?.querySelector('label');
            if (!labelEl) return;

            // Strip out star character and trim label
            let labelText = labelEl.textContent.replace('*', '').trim();
            let val = input.value;

            // Handle dropdown select text
            if (input.tagName.toLowerCase() === 'select') {
                const selectedOption = input.options[input.selectedIndex];
                val = selectedOption ? selectedOption.text : '';
            }

            // Skip empty option values
            if (val === '— Select emergency type —' || val === '— Select type of assistance —' || val === '— Select client type —') {
                return;
            }

            // Create row
            if (val) {
                const row = document.createElement('div');
                row.className = 'summary-row';
                row.innerHTML = `
                    <span class="summary-label">${labelText}</span>
                    <strong class="summary-value">${val}</strong>
                `;
                summaryList.appendChild(row);
            }
        });

        // 2. Collect uploaded files list
        const fileInputs = form.querySelectorAll('input[type="file"]');
        const fileGroup = document.createElement('div');
        fileGroup.className = 'summary-files-group';
        
        let hasFiles = false;
        fileInputs.forEach(input => {
            if (input.files && input.files.length > 0) {
                hasFiles = true;
                const fileName = input.files[0].name;
                const labelText = input.closest('.upload-zone')?.querySelector('.upload-zone-meta strong')?.textContent || 'Document';
                
                const fileRow = document.createElement('div');
                fileRow.className = 'summary-file-row';
                fileRow.innerHTML = `
                    <span class="file-icon"><i class="fas fa-file-pdf"></i></span>
                    <div class="file-info">
                        <strong>${labelText}</strong>
                        <span>${fileName}</span>
                    </div>
                `;
                fileGroup.appendChild(fileRow);
            }
        });

        summaryContainer.appendChild(summaryList);
        
        if (hasFiles) {
            const filesTitle = document.createElement('div');
            filesTitle.className = 'summary-files-title';
            filesTitle.innerHTML = '<i class="fas fa-paperclip"></i> Uploaded Documents';
            summaryContainer.appendChild(filesTitle);
            summaryContainer.appendChild(fileGroup);
        }
    }
});
