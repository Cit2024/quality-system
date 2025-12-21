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
    $('.question').each(function () {
      const $question = $(this);
      if ($question.find('.question-type').data('type') === 'multiple_choice') {
        attachOptionEventListeners($question);
      }
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

    // ========== ACCESS CONTROL EVENT LISTENERS ==========
    $(document).on('click', '.js-open-access-modal', openAccessModal);
    $(document).on('click', '.js-close-access-modal', function(e) {
      if ($(this).closest('#accessControlModal').length > 0) {
        closeAccessModal();
      }
    });
    $(document).on('click', '#accessControlModal .btn-cancel', closeAccessModal);
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

    // ========== TYPE MANAGEMENT EVENT LISTENERS ==========
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
        error: function() { 
          alert('حدث خطأ في الاتصال'); 
        }
      });
    });

    $(document).on('click', '.js-delete-type', function(e) {
      e.stopPropagation();
      const category = $(this).data('category');
      const id = $(this).data('db-id');
      deleteType(category, id);
    });

    // ========== CUSTOM SELECTS EVENT LISTENERS ==========
    $(document).on('click', '.js-custom-select-trigger', function() {
      const type = $(this).data('type');
      toggleCustomSelect(type);
    });

    $(document).on('click', '.js-custom-option', function() {
      const type = $(this).data('type');
      const value = $(this).data('value');
      const name = $(this).data('name') || $(this).find('span').first().text();
      
      selectOption(type, value, name, this);
    });

    // Close selects when clicking outside
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.custom-select-wrapper').length) {
        $('.custom-select-wrapper').removeClass('open');
      }
    });
  }

  // ========== GLOBAL VARIABLES ==========
  let currentAccessFields = [];
  const iconsList = [
    'assets/icons/college.png',
    'assets/icons/user-check.svg',
    'assets/icons/user-graduate-solid.svg',
    'assets/icons/chalkboard-user-solid.svg',
    'assets/icons/building-solid.svg',
    'assets/icons/briefcase-solid.svg',
    'assets/icons/clipboard-list.svg',
    'assets/icons/book-bookmark-solid.svg',
    'assets/icons/star.svg',
    'assets/icons/calendar.svg'
  ];

  // ========== FORM ACTION HANDLERS ==========
  
  function handleFormAction(event) {
    event.stopPropagation();
    const $this = $(this);
    const formId = $this.data("form-id");
    const formTitle = $this.data('form-title');
    const action = $this.data("action");

    if (action === "download") {
      downloadForm(formId, formTitle);
    } else if (action === "delete") {
      if (confirm("هل أنت متأكد أنك تريد حذف هذا النموذج؟")) {
        deleteForm(formId);
      }
    }
  }

  function handleToggleSection(event) {
    if (!$(event.target).closest(".delete-section, .copy-plus").length) {
      toggleSection(this);
    }
  }

  function toggleSection(header) {
    const section = header.parentElement;
    const questionsContainer = section.querySelector(".questions-container");
    const chevron = header.querySelector(".chevron");

    if (questionsContainer.style.display === "block" || questionsContainer.style.display === "") {
      questionsContainer.style.display = "none";
      chevron.innerHTML = `<img src="../assets/icons/chevron-down.svg" alt="Chevron down icon" />`;
    } else {
      questionsContainer.style.display = "block";
      chevron.innerHTML = `<img src="../assets/icons/chevron-up.svg" alt="Chevron up icon" />`;
    }
  }

  function handleFormTypeToggle() {
    const formId = $(".form-details").data("form-id");
    const newType = this.value;
    
    const selectedOption = $(`#type-options .custom-option[data-value="${newType}"]`);
    const allowedTargetsStr = selectedOption.data("targets");
    const allowedTargets = allowedTargetsStr ? String(allowedTargetsStr).split(',') : [];
    
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

    const currentTarget = $('#form-target-select').val();
    if (!allowedTargets.includes(String(currentTarget)) && allowedTargets.length > 0) {
      $('#target-selected-text').text('اختر');
      $('#form-target-select').val('');
    }
    
    updateFormType(formId, newType);
  }

  function handleFormTargetToggle() {
    const formId = $(".form-details").data("form-id");
    const newTarget = this.value;
    updateFormTarget(formId, newTarget);
  }

  function handleFormStatusToggle() {
    const formId = $(".form-details").data("form-id");
    const newStatus = this.checked ? "published" : "draft";
    updateFormStatus(formId, newStatus);
  }

  function handleEditableFieldClick() {
    const $this = $(this);
    const text = $this.text();
    const field = $this.data("field");
    const formId = $this.data("id");

    replaceWithInputField($this, text, (newText) => {
      updateFormField(formId, field, newText);
    });
  }

  function handleEditableNoteClick() {
    const $this = $(this);
    const text = $this.text().trim();
    const formId = $(".form-details").data("form-id");

    replaceWithTextareaField($this, text, (newText) => {
      updateFormNote(formId, newText);
    });
  }

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
  
  function openTypeModal(currentType, questionId, $element) {
    const modalContent = Object.keys(window.TYPE_QUESTION || {}).map(type => `
      <div class="type-option" 
           data-type="${type}"
           role="button"
           tabindex="0"
           aria-label="اختر ${window.TYPE_QUESTION[type].name}">
          <img src="../${window.TYPE_QUESTION[type].icon}" alt="${type}" />
          <span>${window.TYPE_QUESTION[type].name}</span>
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

    $modal.find('.type-option').first().focus();

    $modal.on('click', '.type-option', function () {
      const newType = $(this).data('type');
      handleTypeSelection(newType, currentType, questionId, $element);
      $modal.remove();
    });

    $modal.on('click', (e) => {
      if ($(e.target).hasClass('type-select-modal')) {
        $modal.remove();
      }
    });

    $(document).on('keydown', (e) => {
      if (e.key === 'Escape') $modal.remove();
    });
  }

  function handleTypeSelection(newType, currentType, questionId, $element) {
    if (newType !== currentType) {
      if (confirm(`هل تريد تغيير النوع إلى "${window.TYPE_QUESTION[newType].name}"؟`)) {
        updateQuestionType(questionId, newType, $element);
      }
    }
  }
  
  function handleQuestionTypeChange(e) {
    e.stopPropagation();
    const $this = $(this);
    const questionId = $this.data("id");
    const currentType = $this.data("type");
    
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

  function handleAddSection(event) {
    event.stopPropagation();
    const formId = $(this).data("form-id");
    addNewSection(formId);
  }

  function handleDeleteSection(event) {
    event.stopPropagation();
    const sectionId = $(this).data("section-id");
    if (confirm("هل أنت متأكد أنك تريد حذف هذا القسم؟")) {
      deleteSection(sectionId);
    }
  }

  function handleDeleteQuestion(event) {
    event.stopPropagation();
    const questionId = $(this).data("question-id");
    if (confirm("هل أنت متأكد أنك تريد حذف هذا السؤال؟")) {
      deleteQuestion(questionId);
    }
  }

  function handleAddDefaultQuestion(event) {
    event.stopPropagation();
    const sectionId = $(this).data("section-id");
    addDefaultQuestion(sectionId);
  }

  function handleDownloadForm(event) {
    event.stopPropagation();
    const $button = $(this);
    const formId = $button.data('form-id');
    const formTitle = $button.data('form-title');
    downloadFormEnhanced(formId, formTitle);
  }
   
  function downloadFormEnhanced(formId, formTitle) {
    const $printContainer = $('.view-container').clone();
    $printContainer.find('[data-printthis-ignore]').remove();
    
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

  function replaceWithInputField($element, text, callback) {
    const $input = $("<input type='text' />").val(text);
    $element.html($input);
    
    $input.focus().select();
    
    $input.on("blur", function () {
      const newText = $input.val().trim();
      if (newText === "") {
        alert("لا يمكن ترك الحقل فارغاً!");
        $element.text(text);
      } else {
        $element.text(newText);
        if (newText !== text) {
          callback(newText);
        }
      }
    });
    
    $input.on("keypress", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        $(this).blur();
      }
    });
    
    $input.on("keydown", function (e) {
      if (e.which === 27) {
        e.preventDefault();
        $element.text(text);
        $input.remove();
      }
    });
  }

  function replaceWithTextareaField($element, text, callback) {
    $element.html(`<textarea class="editable-input">${text}</textarea>`);
    const $textarea = $element.find("textarea");
    $textarea.focus().select();

    $textarea.on("blur", function () {
      const newText = $textarea.val().trim();
      $element.text(newText);
      if (newText !== text) {
        callback(newText);
      }
    });
    
    $textarea.on("keydown", function (e) {
      if (e.which === 13 && e.ctrlKey) {
        e.preventDefault();
        $(this).blur();
      } else if (e.which === 27) {
        e.preventDefault();
        $element.text(text);
        $textarea.remove();
      }
    });
  }

  function updateFormType(formId, newType) {
    sendAjaxRequest(
      "./update/update-form-type.php",
      { id: formId, type: newType },
      (response) => {
        showSuccessToast("تم تحديث نوع النموذج بنجاح");
      }
    );
  }

  function updateFormTarget(formId, newTarget) {
    sendAjaxRequest(
      "./update/update-form-target.php",
      { id: formId, target: newTarget },
      (response) => {
        showSuccessToast("تم تحديث وجهة النموذج بنجاح");
      }
    );
  }

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

  function updateFormField(formId, field, value) {
    sendAjaxRequest("./update/update-form.php", {
      id: formId,
      field,
      value,
    });
  }

  function updateFormNote(formId, newNote) {
    sendAjaxRequest(
      "./update/update-form-note.php",
      { id: formId, note: newNote },
      (response) => {
        console.log("Note updated successfully");
      }
    );
  }

  function updateSectionField(sectionId, field, value) {
    sendAjaxRequest("./update/update-section.php", {
      id: sectionId,
      field,
      value,
    });
  }

  function updateQuestionField(questionId, field, value) {
    sendAjaxRequest("./update/update-question.php", {
      id: questionId,
      field,
      value,
    });
  }
  
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
    
    const $question = $element.closest('.question');
    $question.find('.options-section').remove();
    
    if (newType === 'multiple_choice') {
      const optionsHtml = `
        <div class="options-section">
          <label>خيارات الإجابة:</label>
          <div class="options-list"></div>
          <button class="add-option">+ إضافة خيار</button>
        </div>
      `;
      $question.append(optionsHtml);
      attachOptionEventListeners($question);
    }
  }
   
  function attachOptionEventListeners($question) {
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

  function handleAddNoteClick() {
    const $this = $(this);
    const formId = $(".form-details").data("form-id");

    replaceWithTextareaField($this.parent(), "", (newText) => {
      updateFormNote(formId, newText);
    });
  }

  function deleteForm(formId) {
    sendAjaxRequest("./forms/delete/delete-form.php", { id: formId }, (response) => {
      if (response.status === "success") {
        location.reload();
      } else {
        alert("Failed to delete form: " + response.message);
      }
    });
  }

  function addNewSection(formId) {
    sendAjaxRequest("./add/add-new-section.php", { formId }, () =>
      location.reload()
    );
  }

  function deleteSection(sectionId) {
    sendAjaxRequest("./delete/delete-section.php", { id: sectionId }, () =>
      location.reload()
    );
  }

  function deleteQuestion(questionId) {
    sendAjaxRequest(
      "./delete/delete-question.php",
      { id: questionId },
      () => location.reload()
    );
  }

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
  
  function handleCopyLink(event) {
    event.stopPropagation();
    const $button = $(this);
    const link = $button.data('link') || $button.prev('.evaluation-link, .evaluation-link-input').val();
    
    const tempInput = $('<input>');
    $('body').append(tempInput);
    tempInput.val(link).select();
    document.execCommand('copy');
    tempInput.remove();
    
    const originalHtml = $button.html();
    $button.addClass('copied');
    $button.html('<i class="fa-solid fa-check"></i> <span>تم النسخ</span>');
    
    setTimeout(() => {
      $button.removeClass('copied');
      $button.html(originalHtml);
    }, 2000);
  }
  
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

  function showSuccessToast(message) {
    console.log("Success:", message);
  }

  // ========== ACCESS CONTROL FUNCTIONS ==========

  function openAccessModal() {
    console.log("Opening Access Modal...");
    
    if (!window.formConfig) {
      console.error("formConfig is missing!");
      alert("System Error: Configuration missing.");
      return;
    }
    
    console.log("Form Config:", window.formConfig);
    
    $('#access-form-password').val(window.formConfig.password || '');
    
    const tbody = $('#access-fields-tbody');
    tbody.html('<tr><td colspan="5" style="text-align:center;">جاري التحميل...</td></tr>');
    
    $('#accessControlModal').addClass('active').show();
    console.log("Modal displayed");

    $.ajax({
      url: `edit-form.php?id=${window.formConfig.id}&action=get_access_data`,
      method: 'GET',
      dataType: 'json',
      success: function(data) {
        console.log("Access data loaded:", data);
        if(data.success) {
          $('#access-form-password').val(data.password || '');
          currentAccessFields = data.fields || [];
          renderAccessFields();
        } else {
          alert('فشل تحميل البيانات: ' + (data.message || 'خطأ غير معروف'));
        }
      },
      error: function(xhr, status, error) {
        console.error('AJAX Error:', {xhr, status, error});
        tbody.html('<tr><td colspan="5" style="text-align:center; color:red;">خطأ في التحميل</td></tr>');
        alert('خطأ في الاتصال بالخادم');
      }
    });
  }

  function closeAccessModal() {
    console.log("Closing Access Modal");
    $('#accessControlModal').removeClass('active').hide();
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
          
          location.reload();
        } else {
          alert('حدث خطأ: ' + data.message);
        }
      },
      error: function() {
        alert('حدث خطأ في الاتصال');
      }
    });
  }

  // ========== TYPE MANAGEMENT FUNCTIONS ==========

  function openTypeModal(category) {
    console.log("Opening Type Modal for:", category);
    
    $('#typeCategory').val(category);
    $('#modalTitle').text(category === 'target' ? 'إضافة مقيّم جديد' : 'إضافة نوع تقييم جديد');
    $('#typeForm')[0].reset();
    
    const iconContainer = $('.icon-selector');
    iconContainer.empty();
    
    iconsList.forEach(icon => {
      const div = $('<div>')
        .addClass('icon-option js-icon-option')
        .css({
          cursor: 'pointer',
          padding: '5px',
          textAlign: 'center',
          border: '2px solid transparent',
          borderRadius: '4px'
        })
        .html(`<img src="../${icon}" style="width: 24px; height: 24px;">`)
        .data('icon', icon);
        
      iconContainer.append(div);
    });
    
    $('#typeModal').addClass('active').show();
    console.log("Type Modal displayed");
  }

  function closeTypeModal() {
    console.log("Closing Type Modal");
    $('#typeModal').removeClass('active').hide();
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
      dataType: 'json',
      success: function(data) {
        if (data.success) {
          alert('تم الحذف بنجاح');
          location.reload();
        } else {
          alert('خطأ: ' + data.message);
        }
      },
      error: function() {
        alert('حدث خطأ في الاتصال');
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

  // ========== FONT AWESOME ICON PICKER ==========
  
  let fontAwesomeIcons = [];
  
  // Curated list of popular Font Awesome solid icons
  const popularIcons = [
    'book-bookmark', 'graduation-cap', 'chalkboard-user', 'user-tie', 
    'user-graduate', 'briefcase', 'building', 'person-chalkboard',
    'layer-group', 'clipboard-list', 'user-check', 'users', 'calendar',
    'chart-bar', 'chart-line', 'chart-pie', 'file-alt', 'folder',
    'home', 'cog', 'user', 'envelope', 'phone', 'map-marker-alt',
    'star', 'heart', 'check', 'times', 'plus', 'minus', 'edit',
    'trash', 'download', 'upload', 'search', 'filter', 'sort',
    'bell', 'comment', 'share', 'link', 'lock', 'unlock',
    'eye', 'eye-slash', 'print', 'save', 'copy', 'paste',
    'lightbulb', 'rocket', 'trophy', 'medal', 'award', 'certificate',
    'book', 'bookmark', 'pen', 'pencil', 'highlighter', 'eraser',
    'calculator', 'compass', 'globe', 'flag', 'shield', 'crown'
  ];
  
  function loadFontAwesomeIcons() {
    // Use curated list of popular icons
    fontAwesomeIcons = popularIcons.map(icon => ({
      name: icon,
      className: `fa-solid fa-${icon}`
    }));
    
    renderIcons(fontAwesomeIcons);
  }
  
  function renderIcons(icons) {
    const iconSelector = $('.icon-selector');
    iconSelector.empty();
    
    if (icons.length === 0) {
      iconSelector.html(`
        <div style="grid-column: 1 / -1; text-align: center; padding: 20px; color: #999;">
          لم يتم العثور على أيقونات
        </div>
      `);
      return;
    }
    
    icons.forEach(icon => {
      const iconElement = $(`
        <div class="icon-option" data-icon="${icon.className}" style="
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 12px;
          border: 2px solid transparent;
          border-radius: 4px;
          cursor: pointer;
          transition: all 0.2s;
          background: white;
        " title="${icon.name}">
          <i class="${icon.className}" style="font-size: 24px; color: #333;"></i>
        </div>
      `);
      
      iconElement.on('mouseenter', function() {
        $(this).css({backgroundColor: '#e3f2fd', transform: 'scale(1.1)'});
      });
      
      iconElement.on('mouseleave', function() {
        if (!$(this).hasClass('selected')) {
          $(this).css({backgroundColor: 'white', transform: 'scale(1)'});
        }
      });
      
      iconElement.on('click', function() {
        $('.icon-option').removeClass('selected').css({
          borderColor: 'transparent',
          backgroundColor: 'white',
          transform: 'scale(1)'
        });
        
        $(this).addClass('selected').css({
          borderColor: '#2196F3',
          backgroundColor: '#e3f2fd',
          transform: 'scale(1.05)'
        });
        
        $('#typeIcon').val(icon.className);
        $('#selectedIconDisplay').attr('class', icon.className);
        $('#selectedIconName').text(icon.className);
        $('#selectedIconPreview').show();
      });
      
      iconSelector.append(iconElement);
    });
  }
  
  // Search functionality
  $(document).on('input', '#iconSearch', function() {
    const searchTerm = $(this).val().toLowerCase();
    
    if (!searchTerm) {
      renderIcons(fontAwesomeIcons);
      return;
    }
    
    const filteredIcons = fontAwesomeIcons.filter(icon => 
      icon.name.toLowerCase().includes(searchTerm)
    );
    
    renderIcons(filteredIcons);
  });
  
  // Initialize icon picker when modal opens
  $(document).on('click', '.js-open-type-modal', function() {
    if (fontAwesomeIcons.length === 0) {
      loadFontAwesomeIcons();
    }
  });

});