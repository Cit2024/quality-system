$(document).ready(function () {
  // Initialize all event listeners
  initializeEventListeners();

  // Function to initialize all event listeners
  function initializeEventListeners() {
    
    // Event listener for copy link buttons
    $(document).on("click", ".copy-link-btn", handleCopyLink);

    // Event listener for form actions (download and delete)
    $(document).on("click", ".form-action-button", handleFormAction);

    // Event listener for toggling section visibility
    $(document).on("click", ".section-header", handleToggleSection);

    // Event listeners for form toggles (type, target, status)
    $(document).on("change", "#form-type-select", handleFormTypeToggle);
    $(document).on("change", "#form-target-select", handleFormTargetToggle);
    $(document).on("change", "#form-status-toggle", handleFormStatusToggle);

    // Event listeners for editable fields (form, note form, section, question)
    $(document).on("click", ".editable", handleEditableFieldClick);
    $(document).on("click", ".editable-note", handleEditableNoteClick);
    $(document).on("click", ".editable-section", handleEditableSectionClick);
    $(document).on("click", ".editable-question", handleEditableQuestionClick);

    // Event listeners for question type changes
    $(document).on("click", ".question-type", handleQuestionTypeChange);
    
    // Initialize options for existing multiple_choice questions on page load
    $(document).ready(function () {
      $('.question').each(function () {
        const $question = $(this);
        if ($question.find('.question-type').data('type') === 'multiple_choice') {
          attachOptionEventListeners($question);
        }
      });
    });

    // Event listeners for adding and deleting sections/questions
    $(document).on("click", ".add-section-button", handleAddSection);
    $(document).on("click", ".delete-section", handleDeleteSection);
    $(document).on("click", ".delete-question", handleDeleteQuestion);
    $(document).on("click", ".copy-plus", handleAddDefaultQuestion);

    // Event listener for adding a new note (when no note exists)
    $(document).on("click", ".add-note-button", handleAddNoteClick);

    // Event listener for the download form button
    $(document).on("click", ".download-form-button", handleDownloadForm);
  }

  // Function to handle form actions (download or delete)
  function handleFormAction(event) {
    event.stopPropagation(); // Prevent the card click event from firing
    const $this = $(this);
    const formId = $this.data("form-id"); // Get the form ID from the button
    const formTitle = $this.data('form-title');
    const action = $this.data("action"); // Get the action (download or delete)

    if (action === "download") {
      downloadForm(formId, formTitle); // Call the download function
    } else if (action === "delete") {
      if (confirm("هل أنت متأكد أنك تريد حذف هذا النموذج؟")) {
        deleteForm(formId); // Call the delete function
      }
    }
  }

  // Function to handle toggling section visibility
  function handleToggleSection(event) {
    // Ensure the click is not on a child element that should not trigger the toggle
    if (!$(event.target).closest(".delete-section, .copy-plus").length) {
      toggleSection(this);
    }
  }

  // Function to toggle section visibility
  function toggleSection(header) {
    const section = header.parentElement;
    const questionsContainer = section.querySelector(".questions-container");
    const chevron = header.querySelector(".chevron");

    if (
      questionsContainer.style.display === "block" ||
      questionsContainer.style.display === ""
    ) {
      questionsContainer.style.display = "none";
      chevron.innerHTML = `<img src="../assets/icons/chevron-down.svg" alt="Chevron down icon" />`;
    } else {
      questionsContainer.style.display = "block";
      chevron.innerHTML = `<img src="../assets/icons/chevron-up.svg" alt="Chevron up icon" />`;
    }
  }

  // Function to handle FormType toggle
  function handleFormTypeToggle() {
    const formId = $(".form-details").data("form-id");
    const newType = this.value;
    
    // Get allowed targets from the custom option element
    const selectedOption = $(`#type-options .custom-option[data-value="${newType}"]`);
    const allowedTargetsStr = selectedOption.data("targets");
    const allowedTargets = allowedTargetsStr ? String(allowedTargetsStr).split(',') : [];
    
    // Filter FormTarget options
    const $targetOptions = $("#target-options .custom-option");
    
    $targetOptions.each(function () {
      const targetVal = $(this).data('value');
      const isAllowed = allowedTargets.includes(String(targetVal));
      
      if (isAllowed) {
        $(this).show();
      } else {
        $(this).hide();
      }
    });

    // Reset target selection if current target is hidden
    const currentTarget = $('#form-target-select').val();
    if (!allowedTargets.includes(String(currentTarget)) && allowedTargets.length > 0) {
        // Automatically select the first visible option or reset
        // For now, let's just leave it or user will see "Select" text if we reset UI only
        // Implementation choice: Keep it simple. User must select new target.
        $('#target-selected-text').text('اختر');
        $('#form-target-select').val('');
        // NOTE: We could auto-select the first valid one here.
    }
    
    updateFormType(formId, newType);
  }

  // Function to handle FormTarget toggle
  function handleFormTargetToggle() {
    const formId = $(".form-details").data("form-id");
    const newTarget = this.value;
    updateFormTarget(formId, newTarget);
  }

  // Function to handle FormStatus toggle
  function handleFormStatusToggle() {
    const formId = $(".form-details").data("form-id");
    const newStatus = this.checked ? "published" : "draft";
    updateFormStatus(formId, newStatus);
  }

  // Function to handle editable field clicks
  function handleEditableFieldClick() {
    const $this = $(this);
    const text = $this.text();
    const field = $this.data("field");
    const formId = $this.data("id");

    replaceWithInputField($this, text, (newText) => {
      updateFormField(formId, field, newText);
    });
  }

  // Function to handle editable note clicks
  function handleEditableNoteClick() {
    const $this = $(this);
    const text = $this.text().trim(); // Get the current note text
    const formId = $(".form-details").data("form-id"); // Get the form ID

    // Replace the note text with an input field
    replaceWithTextareaField($this, text, (newText) => {
      updateFormNote(formId, newText); // Save the updated note
    });
  }

  // Function to handle editable section title clicks
  function handleEditableSectionClick(e) {
    e.stopPropagation();
    const $this = $(this);
    const text = $this.text();
    const field = $this.data("field");
    const sectionId = $this.data("id");

    replaceWithInputField($this, text, (newText) => {
      updateSectionField(sectionId, field, newText);
    });
  }

  // Function to handle editable question clicks
  function handleEditableQuestionClick(e) {
    e.stopPropagation();
    const $this = $(this);
    const text = $this.text();
    const field = $this.data("field");
    const questionId = $this.data("id");

    replaceWithInputField($this, text, (newText) => {
      updateQuestionField(questionId, field, newText);
    });
  }
  
  // Function to open the popup window
  function openTypeModal(currentType, questionId, $element) {
    // Create window content
    const modalContent = Object.keys(TYPE_QUESTION).map(type => `
        <div class="type-option" 
             data-type="${type}"
             role="button"
             tabindex="0"
             aria-label="اختر ${TYPE_QUESTION[type].name}">
            <img src="../${TYPE_QUESTION[type].icon}" alt="${type}" />
            <span>${TYPE_QUESTION[type].name}</span>
        </div>
    `).join('');

    const $modal = $(`
        <div class="type-select-modal" role="dialog" aria-modal="true">
            <div class="modal-content">
                <h3 id="modal-title">اختر نوع السؤال</h3>
                ${modalContent}
            </div>
        </div>
    `).appendTo('body').addClass('active');

    // Manage focus of the window
    $modal.find('.type-option').first().focus();

    // Type selection events
    $modal.on('click', '.type-option', function () {
      const newType = $(this).data('type');
      handleTypeSelection(newType, currentType, questionId, $element);
      $modal.remove();
    });

    // Close the window when clicked outside it
    $modal.on('click', (e) => {
      if ($(e.target).hasClass('type-select-modal')) {
        $modal.remove();
      }
    });

    // Close with keyboard (Esc)
    $(document).on('keydown', (e) => {
      if (e.key === 'Escape') $modal.remove();
    });
  }

  // Type selection processing function
  function handleTypeSelection(newType, currentType, questionId, $element) {
    if (newType !== currentType) {
      if (confirm(`هل تريد تغيير النوع إلى "${TYPE_QUESTION[newType].name}"؟`)) {
        updateQuestionType(questionId, newType, $element);
      }
    }
  }
  
  // Function to handle question type changes
  function handleQuestionTypeChange(e) {
    e.stopPropagation();
    const $this = $(this);
    const questionId = $this.data("id");
    const currentType = $this.data("type");
    
    // Create a modal with options
    openTypeModal(currentType, questionId, $this);
  }
    
  function addQuestionOption(questionId, optionText, callback) {
    sendAjaxRequest("./add/add-question-option.php", {
      questionId: questionId,
      option: optionText
    }, callback);
  }

  function removeQuestionOption(questionId, optionText, callback) {
    sendAjaxRequest("./delete/remove-question-option.php", {
      questionId: questionId,
      option: optionText
    }, callback);
  }

  // Function to handle adding a new section
  function handleAddSection(event) {
    event.stopPropagation();
    const formId = $(this).data("form-id");
    addNewSection(formId);
  }

  // Function to handle deleting a section
  function handleDeleteSection(event) {
    event.stopPropagation();
    const sectionId = $(this).data("section-id");
    if (confirm("هل أنت متأكد أنك تريد حذف هذا القسم؟")) {
      deleteSection(sectionId);
    }
  }

  // Function to handle deleting a question
  function handleDeleteQuestion(event) {
    event.stopPropagation();
    const questionId = $(this).data("question-id");
    if (confirm("هل أنت متأكد أنك تريد حذف هذا السؤال؟")) {
      deleteQuestion(questionId);
    }
  }

  // Function to handle adding a default question
  function handleAddDefaultQuestion(event) {
    event.stopPropagation();
    const sectionId = $(this).data("section-id");
    addDefaultQuestion(sectionId);
  }

  // Function to handle downloading the form as a PDF
  function handleDownloadForm(event) {
    event.stopPropagation();
    const $button = $(this);
    const formId = $button.data('form-id');
    const formTitle = $button.data('form-title');
    downloadFormEnhanced(formId, formTitle);
  }
   
  // Enhanced PDF download function
  function downloadFormEnhanced(formId, formTitle) {
    // Clone the container
    const $printContainer = $('.view-container').clone();
        
    // Remove elements that shouldn't be printed
    $printContainer.find('[data-printthis-ignore]').remove();
        
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>${formTitle}</title>
                <link rel="stylesheet" href=".././styles/forms.css" media="print">
                <style>
                    @media print {
                      body { 
                        direction: rtl;
                        font-family: 'DINRegular', sans-serif;
                        padding: 0 20px !important;
                      }
                      .section-header .chevron,
                      .questions-container {
                        display: block !important;
                      }
                }
                </style>
            </head>
            <body>
                ${$printContainer.html()}
                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                            window.close();
                        }, 200);
                    }
                <\/script>
            </body>
            </html>
        `);
    printWindow.document.close();
  }

  // Function to replace text with an input field
  function replaceWithInputField($element, text, callback) {
    const $input = $("<input type='text' />").val(text);
    $element.html($input);
        
    // Select all text when input is focused
    $input.focus().select();
        
    // Handle blur event
    $input.on("blur", function () {
      const newText = $input.val().trim();
      if (newText === "") {
        alert("لا يمكن ترك الحقل فارغاً!");
        $element.text(text); // Restore original text
      } else {
        $element.text(newText);
        if (newText !== text) { // Only call callback if text changed
          callback(newText);
        }
      }
    });
        
    // Handle Enter key to save
    $input.on("keypress", function (e) {
      if (e.which === 13) { // Enter key
        e.preventDefault();
        $(this).blur();
      }
    });
        
    // Handle Escape key to cancel
    $input.on("keydown", function (e) {
      if (e.which === 27) { // Escape key
        e.preventDefault();
        $element.text(text); // Restore original text
        $input.remove();
      }
    });
  }

  // Function to replace text with a textarea field
  function replaceWithTextareaField($element, text, callback) {
    $element.html(`<textarea class="editable-input">${text}</textarea>`);
    const $textarea = $element.find("textarea");
    $textarea.focus().select();

    // Save the note when the textarea loses focus
    $textarea.on("blur", function () {
      const newText = $textarea.val().trim();
      $element.text(newText); // Replace the textarea with the updated note
      if (newText !== text) { // Only call callback if text changed
        callback(newText); // Call the callback to save the note
      }
    });
    
    // Handle Ctrl+Enter to save
    $textarea.on("keydown", function (e) {
      if (e.which === 13 && e.ctrlKey) { // Ctrl+Enter
        e.preventDefault();
        $(this).blur();
      } else if (e.which === 27) { // Escape key to cancel
        e.preventDefault();
        $element.text(text); // Restore original text
        $textarea.remove();
      }
    });
  }

  // Function to update FormType
  function updateFormType(formId, newType) {
    sendAjaxRequest(
      "./update/update-form-type.php",
      { id: formId, type: newType },
      (response) => {
        showSuccessToast("تم تحديث نوع النموذج بنجاح");
      }
    );
  }

  // Function to update FormTarget
  function updateFormTarget(formId, newTarget) {
    sendAjaxRequest(
      "./update/update-form-target.php",
      { id: formId, target: newTarget },
      (response) => {
        showSuccessToast("تم تحديث وجهة النموذج بنجاح");
      }
    );
  }

  // Function to update FormStatus
  function updateFormStatus(formId, newStatus) {
    sendAjaxRequest(
      "./update/update-form-status.php",
      { id: formId, status: newStatus },
      (response) => {
        const formStatus = $(".form-status");
        formStatus.html(
          newStatus === "published"
            ? `<img src=".././assets/icons/badge-check.svg" alt="Published" /><span>منشور</span>`
            : `<img src=".././assets/icons/badge-alert.svg" alt="Draft" /><span>مسودة</span>`
        );
      }
    );
  }

  // Function to update a form field
  function updateFormField(formId, field, value) {
    sendAjaxRequest("./update/update-form.php", {
      id: formId,
      field,
      value,
    });
  }

  // Function to update the form note
  function updateFormNote(formId, newNote) {
    sendAjaxRequest(
      "./update/update-form-note.php",
      { id: formId, note: newNote },
      (response) => {
        // Optional: Show a success message or update the UI
        console.log("Note updated successfully");
      }
    );
  }

  // Function to update a section field
  function updateSectionField(sectionId, field, value) {
    sendAjaxRequest("./update/update-section.php", {
      id: sectionId,
      field,
      value,
    });
  }

  // Function to update a question field
  function updateQuestionField(questionId, field, value) {
    sendAjaxRequest("./update/update-question.php", {
      id: questionId,
      field,
      value,
    });
  }
  
  // New visual update handler
  function updateTypeVisuals($element, newType) {
    const icons = {
      'multiple_choice': 'list-check',
      'true_false': 'square-check',
      'evaluation': 'star',
      'essay': 'quote'
    };
    
    const labels = {
      'multiple_choice': 'إختيار من متعدد',
      'true_false': 'صح/خطأ',
      'evaluation': 'تقييم',
      'essay': 'مقالي'
    };

    $element.html(`
        <img src=".././assets/icons/${icons[newType]}.svg" alt="${newType}" />
        <span>${labels[newType]}</span>
    `);
    
    // Get parent question element
    const $question = $element.closest('.question');
    
    // Remove existing options section
    $question.find('.options-section').remove();
    
    // Add options section ONLY if the new type is multiple_choice
    if (newType === 'multiple_choice') {
      const optionsHtml = `
            <div class="options-section">
                <label>خيارات الإجابة:</label>
                <div class="options-list"></div>
                <button class="add-option">+ إضافة خيار</button>
            </div>
        `;
      $question.append(optionsHtml);
        
      // Attach event listeners to the new buttons
      attachOptionEventListeners($question);
    }
  }
   
  
  function attachOptionEventListeners($question) {
    // Add Option
    $question.find('.add-option').off('click').on('click', function (e) {
      e.stopPropagation();
      const questionId = $question.find('.question-type').data('id');
      const newOption = prompt("أدخل الخيار الجديد:");
      if (newOption) {
        addQuestionOption(questionId, newOption, (response) => {
          const optionHtml = `
                    <div class="option-item" data-option="${newOption}">
                        <p class="option-value">${newOption}</p>
                        <button class="remove-option" data-printthis-ignore><i class="fa-solid fa-trash"></i></button>
                    </div>
                `;
          $question.find('.options-list').append(optionHtml);
        });
      }
    });

    $question.off('click', '.remove-option').on('click', '.remove-option', function (e) {
      e.stopPropagation();
      const $optionItem = $(this).closest('.option-item');
      const questionId = $question.find('.question-type').data('id');
      const optionText = $optionItem.data('option');
        
      if (confirm("حذف هذا الخيار؟")) {
        removeQuestionOption(questionId, optionText, () => {
          $optionItem.remove();
        });
      }
    });
  }

  // Function to update question type
  function updateQuestionType(questionId, newType, $element) {
    sendAjaxRequest(
      "./update/update-question-type.php",
      { id: questionId, type: newType },
      (response) => {
        $element.data("type", newType);
        updateTypeVisuals($element, newType);
      }
    );
  }

  // Function to handle adding a new note
  function handleAddNoteClick() {
    const $this = $(this);
    const formId = $(".form-details").data("form-id"); // Get the form ID

    // Replace the button with a textarea for editing
    replaceWithTextareaField($this.parent(), "", (newText) => {
      updateFormNote(formId, newText); // Save the updated note
    });
  }

  // Function to delete a form
  function deleteForm(formId) {
    sendAjaxRequest("./forms/delete/delete-form.php", { id: formId }, (response) => {
      if (response.status === "success") {
        location.reload(); // Reload the page after successful deletion
      } else {
        alert("Failed to delete form: " + response.message); // Show an error message
      }
    });
  }

  // Function to add a new section
  function addNewSection(formId) {
    sendAjaxRequest("./add/add-new-section.php", { formId }, () =>
      location.reload()
    );
  }

  // Function to delete a section
  function deleteSection(sectionId) {
    sendAjaxRequest("./delete/delete-section.php", { id: sectionId }, () =>
      location.reload()
    );
  }

  // Function to delete a question
  function deleteQuestion(questionId) {
    sendAjaxRequest(
      "./delete/delete-question.php",
      { id: questionId },
      () => location.reload()
    );
  }

  // Function to add a default question
  function addDefaultQuestion(sectionId) {
    sendAjaxRequest("./add/add-default-question.php", { sectionId }, () =>
      location.reload()
    );
  }

  function downloadForm(formId, formTitle) {
    const $spinner = $('<div class="pdf-loading-spinner"></div>').appendTo('body');
    const url = `./forms/edit-form.php?id=${formId}`;

    fetch(url)
      .then(response => response.text())
      .then(html => {
        const $tempContainer = $('<div>').html(
          html.replace(/\.\.(\/\.?)?\/assets/g, '/quality-system/assets')
        );
            
        $tempContainer.find('[data-printthis-ignore]').remove();
            
        // Fallback to basic printing if printThis isn't available
        if (typeof $.fn.printThis !== 'function') {
          console.warn('printThis plugin not loaded, using fallback printing');
          return fallbackPrint($tempContainer, formTitle);
        }
            
        $tempContainer.printThis({
          importCSS: true,
          loadCSS: "/quality-system/styles/forms.css",
          pageTitle: formTitle,
          beforePrint: () => {
            $('head').append(`
                        <style>
                            @media print {
                                body { 
                                    direction: rtl;
                                    font-family: 'DINRegular', sans-serif;
                                    padding: 0 20px !important;
                                }
                                .section-header .chevron,
                                .questions-container {
                                    display: block !important;
                                }
                            }
                        </style>
                    `);
          },
          afterPrint: () => {
            $spinner.remove();
            $tempContainer.remove();
          }
        });
      })
      .catch(error => {
        console.error("Error:", error);
        alert("فشل تنزيل النموذج. يرجى المحاولة مرة أخرى.");
      })
      .finally(() => $spinner.remove());
  }

  // Fallback printing function
  function fallbackPrint($content, title) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>${title}</title>
                <link rel="stylesheet" href="/quality-system/styles/forms.css" media="print">
                <style>
                    @media print {
                        body { 
                            direction: rtl;
                            font-family: 'DINRegular', sans-serif;
                            padding: 0 20px !important;
                        }
                        .section-header .chevron,
                        .questions-container {
                            display: block !important;
                        }
                    }
                </style>
            </head>
            <body>
                ${$content.html()}
                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                            window.close();
                        }, 200);
                    }
                <\/script>
            </body>
            </html>
        `);
    printWindow.document.close();
  }
  
  // Function to handle copy link
  function handleCopyLink(event) {
    event.stopPropagation();
    const $button = $(this);
    const link = $button.data('link') || $button.prev('.evaluation-link, .evaluation-link-input').val();
    
    // Copy to clipboard
    const tempInput = $('<input>');
    $('body').append(tempInput);
    tempInput.val(link).select();
    document.execCommand('copy');
    tempInput.remove();
    
    // Visual feedback
    const originalHtml = $button.html();
    $button.addClass('copied');
    $button.html('<i class="fa-solid fa-check"></i> <span>تم النسخ</span>');
    
    setTimeout(() => {
      $button.removeClass('copied');
      $button.html(originalHtml);
    }, 2000);
  }
  
  // Generic function to send AJAX requests
  function sendAjaxRequest(url, data, successCallback) {
    $.ajax({
      url,
      method: "POST",
      data,
      success: function (response) {
        const result = JSON.parse(response);
        if (result.status === "success") {
          if (successCallback) successCallback(result);
        } else {
          alert("Failed: " + result.message);
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX error:", error);
      },
    });
  }

    /* =========================================
    /* =========================================
     Access Control & Type Management Logic
     Integrated into forms.js style
     ========================================= */

  let currentAccessFields = [];
  const iconsList = [
    'assets/icons/college.png',
    'assets/icons/user.svg',
    'assets/icons/users.svg',
    'assets/icons/star.svg',
    'assets/icons/file-text.svg',
    'assets/icons/clipboard.svg',
    'assets/icons/check-circle.svg',
    'assets/icons/calendar.svg'
  ];

  // --- Access Control Functions ---

  function openAccessModal() {
    console.log("Opening Access Modal...");
    if (!window.formConfig) {
        console.error("formConfig is missing!");
        alert("System Error: Configuration missing.");
        return;
    }
    
    $('#access-form-password').val(window.formConfig.password);
    
    // Show loading state
    const tbody = $('#access-fields-tbody');
    tbody.html('<tr><td colspan="4" style="text-align:center;">جاري التحميل...</td></tr>');
    $('#accessControlModal').show();

    $.ajax({
      url: `edit-form.php?id=${window.formConfig.id}&action=get_access_data`,
      method: 'GET',
      dataType: 'json',
      success: function(data) {
        if(data.success) {
          $('#access-form-password').val(data.password);
          currentAccessFields = data.fields;
          renderAccessFields();
        } else {
          alert('فشل تحميل البيانات');
        }
      },
      error: function(err) {
        console.error('Error:', err);
        tbody.html('<tr><td colspan="4" style="text-align:center; color:red;">خطأ في التحميل</td></tr>');
      }
    });
  }

  function closeAccessModal() {
    $('#accessControlModal').hide();
  }

  function renderAccessFields() {
    const tbody = $('#access-fields-tbody');
    tbody.empty();
    
    currentAccessFields.forEach((field, index) => {
      const row = `
        <tr>
          <td><input type="text" class="js-access-input" data-index="${index}" data-key="Label" value="${field.Label}" placeholder="مثال: رقم الطالب"></td>
          <td><input type="text" class="js-access-input" data-index="${index}" data-key="Slug" value="${field.Slug || ''}" placeholder="IDStudent" style="direction: ltr;"></td>
          <td>
            <select class="js-access-input" data-index="${index}" data-key="FieldType">
              <option value="text" ${field.FieldType === 'text' ? 'selected' : ''}>نص</option>
              <option value="number" ${field.FieldType === 'number' ? 'selected' : ''}>رقم</option>
              <option value="email" ${field.FieldType === 'email' ? 'selected' : ''}>بريد إلكتروني</option>
              <option value="date" ${field.FieldType === 'date' ? 'selected' : ''}>تاريخ</option>
              <option value="password" ${field.FieldType === 'password' ? 'selected' : ''}>كلمة مرور</option>
            </select>
          </td>
          <td style="text-align: center;">
            <input type="checkbox" class="js-access-checkbox" data-index="${index}" data-key="IsRequired" ${field.IsRequired == 1 ? 'checked' : ''} style="width: auto;">
          </td>
          <td style="text-align: center;">
            <button type="button" class="btn-remove-row js-remove-access-row" data-index="${index}">
              <i class="fa-solid fa-trash"></i>
            </button>
          </td>
        </tr>
      `;
      tbody.append(row);
    });
  }

  function addAccessFieldRow() {
    currentAccessFields.push({
      ID: null,
      Label: '',
      Slug: '',
      FieldType: 'text',
      IsRequired: 1
    });
    renderAccessFields();
  }

  function removeAccessFieldRow(index) {
    currentAccessFields.splice(index, 1);
    renderAccessFields();
  }

  function updateAccessField(index, key, value) {
    currentAccessFields[index][key] = value;
  }

  function saveAccessSettings() {
    const password = $('#access-form-password').val();
    
    for (let field of currentAccessFields) {
      if (!field.Label.trim()) {
        alert('يرجى إدخال اسم لجميع الحقول');
        return;
      }
    }

    const formData = new FormData();
    formData.append('action', 'save_access_settings');
    formData.append('form_id', window.formConfig.id);
    formData.append('password', password);
    formData.append('fields', JSON.stringify(currentAccessFields));

    $.ajax({
      url: 'edit-form.php?id=' + window.formConfig.id,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function(data) {
        if (data.success) {
          alert('تم حفظ الإعدادات بنجاح');
          closeAccessModal();
          
          if (data.eval_link) {
            $('.evaluation-link-input').val(data.eval_link);
          }
        } else {
          alert('حدث خطأ: ' + data.message);
        }
      },
      error: function() {
        alert('حدث خطأ في الاتصال');
      }
    });
  }

  // --- Type Management Functions ---

  function openTypeModal(category) {
    $('#typeCategory').val(category);
    $('#modalTitle').text(category === 'target' ? 'إضافة مقيّم جديد' : 'إضافة نوع تقييم جديد');
    $('#typeForm')[0].reset();
    
    // Populate icons
    const iconContainer = $('.icon-selector');
    iconContainer.empty();
    
    iconsList.forEach(icon => {
      const div = $('<div>')
        .addClass('icon-option js-icon-option')
        .css({
          cursor: 'pointer',
          padding: '5px',
          textAlign: 'center',
          border: '1px solid transparent'
        })
        .html(`<img src="../${icon}" style="width: 24px; height: 24px;">`)
        .data('icon', icon);
        
      iconContainer.append(div);
    });
    
    $('#typeModal').show();
  }

  function closeTypeModal() {
    $('#typeModal').hide();
  }

  function deleteType(category, id) {
    if (!id) {
      alert('لا يمكن حذف هذا العنصر');
      return;
    }

    if (!confirm('هل أنت متأكد من الحذف؟')) return;

    $.ajax({
      url: window.location.href,
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ 
        action: 'delete_type',
        id: id,
        category: category
      }),
      success: function(data) {
        if (data.success) {
          alert('تم الحذف بنجاح');
          location.reload();
        } else {
          alert('خطأ: ' + data.message);
        }
      }
    });
  }

  function toggleCustomSelect(type) {
    const wrapper = $('#' + type + '-select-wrapper');
    const isOpen = wrapper.hasClass('open');
    
    $('.custom-select-wrapper').removeClass('open');
    
    if (!isOpen) {
      wrapper.addClass('open');
    }
  }

  function selectOption(type, value, name, element) {
    const inputId = type === 'target' ? 'form-target-select' : 'form-type-select';
    const inputElement = $('#' + inputId);
    
    inputElement.val(value).trigger('change');
    
    $('#' + type + '-selected-text').text(name);
    
    const wrapper = $('#' + type + '-select-wrapper');
    wrapper.find('.custom-option').removeClass('selected');
    $(element).addClass('selected');
    
    wrapper.removeClass('open');
  }

  // --- Event Listeners Registration ---

  // Access Control Modals
  $(document).on('click', '.js-open-access-modal', openAccessModal);
  $(document).on('click', '.js-close-access-modal, .btn-cancel', function(e) {
      // Check if it's inside access modal
      if ($(this).closest('#accessControlModal').length > 0) {
          closeAccessModal();
      }
  });
  $(document).on('click', '.js-save-access-settings', saveAccessSettings);
  $(document).on('click', '.js-add-access-row', addAccessFieldRow);
  
  // Dynamic Access Fields Inputs
  $(document).on('change', '.js-access-input', function() {
    const index = $(this).data('index');
    const key = $(this).data('key');
    updateAccessField(index, key, $(this).val());
  });
  
  $(document).on('change', '.js-access-checkbox', function() {
    const index = $(this).data('index');
    const key = $(this).data('key');
    updateAccessField(index, key, $(this).is(':checked') ? 1 : 0);
  });
  
  $(document).on('click', '.js-remove-access-row', function() {
    const index = $(this).data('index');
    removeAccessFieldRow(index);
  });

  // Type Management
  $(document).on('click', '.js-open-type-modal', function() {
      const category = $(this).data('category');
      openTypeModal(category);
  });
  $(document).on('click', '.js-close-type-modal', closeTypeModal);
  
  $(document).on('click', '.js-icon-option', function() {
      $('.icon-option').css('borderColor', 'transparent');
      $(this).css('borderColor', '#2196F3');
      $('#typeIcon').val($(this).data('icon'));
  });

  $(document).on('submit', '#typeForm', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      formData.append('action', 'create_type');
      
      $.ajax({
          url: window.location.href,
          method: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function(data) {
              if (data.success) {
                  alert('تم الإضافة بنجاح');
                  location.reload(); 
              } else {
                  alert('خطأ: ' + data.message);
              }
          },
          error: function() { alert('حدث خطأ في الاتصال'); }
      });
  });

  $(document).on('click', '.js-delete-type', function(e) {
      e.stopPropagation();
      const category = $(this).data('category');
      const id = $(this).data('db-id');
      deleteType(category, id);
  });

  // Custom Selects
  $(document).on('click', '.js-custom-select-trigger', function() {
      const type = $(this).data('type');
      toggleCustomSelect(type);
  });

  $(document).on('click', '.js-custom-option', function() {
      const type = $(this).data('type');
      const value = $(this).data('value');
      const name = $(this).data('name'); // We need to add data-name to HTML
      
      // If name isn't in data attribute (legacy), try to get text
      const finalName = name || $(this).find('span').first().text();
      
      selectOption(type, value, finalName, this);
  });

  // Close selects when clicking outside
  $(document).on('click', function(e) {
      if (!$(e.target).closest('.custom-select-wrapper').length) {
          $('.custom-select-wrapper').removeClass('open');
      }
  });

});