### Project Structure

```plaintext
quality-system
├── assets/
│   ├── fonts/
│   │   ├── DINNEXTLTARABIC-LIGHT-2-2.ttf [171.79 KB]
│   │   ├── DINNextLTArabic-Bold-4.ttf [155.64 KB]
│   │   ├── DINNextLTArabic-Regular-4.ttf [159.16 KB]
│   │   ├── NotoKufiArabic-Black.ttf [176.60 KB]
│   │   ├── NotoKufiArabic-Bold.ttf [176.34 KB]
│   │   ├── NotoKufiArabic-ExtraBold.ttf [176.69 KB]
│   │   ├── NotoKufiArabic-ExtraLight.ttf [176.61 KB]
│   │   ├── NotoKufiArabic-Light.ttf [176.69 KB]
│   │   ├── NotoKufiArabic-Medium.ttf [176.48 KB]
│   │   ├── NotoKufiArabic-Regular.ttf [176.23 KB]
│   │   ├── NotoKufiArabic-SemiBold.ttf [176.48 KB]
│   │   └── NotoKufiArabic-Thin.ttf [176.40 KB]
│   └── icons/
│       ├── Industrial-Technology-College-Logo-Arabic-For-the-big-screen.svg [91.83 KB]
│       ├── badge-alert.svg [467 bytes]
│       ├── badge-check.svg [412 bytes]
│       ├── book-bookmark-solid.svg [539 bytes]
│       ├── briefcase-solid.svg [584 bytes]
│       ├── building-solid.svg [506 bytes]
│       ├── calendar.svg [443 bytes]
│       ├── chalkboard-user-solid.svg [652 bytes]
│       ├── checkmark.svg [519 bytes]
│       ├── chevron-down.svg [246 bytes]
│       ├── chevron-right.svg [243 bytes]
│       ├── chevron-up.svg [246 bytes]
│       ├── circle-check-solid.svg [424 bytes]
│       ├── circle-user-round.svg [323 bytes]
│       ├── clipboard-list.svg [439 bytes]
│       ├── college.png [53.90 KB]
│       ├── copy-plus.svg [420 bytes]
│       ├── copy.svg [332 bytes]
│       ├── cross.svg [773 bytes]
│       ├── eye-closed.svg [368 bytes]
│       ├── eye.svg [352 bytes]
│       ├── file-down.svg [365 bytes]
│       ├── file-plus.svg [364 bytes]
│       ├── files.svg [1.11 KB]
│       ├── folder-clock.svg [409 bytes]
│       ├── graduation-cap-solid.svg [990 bytes]
│       ├── layer-group-solid.svg [343 bytes]
│       ├── list-check.svg [305 bytes]
│       ├── minus-solid.svg [331 bytes]
│       ├── no-data.svg [333 bytes]
│       ├── o-solid.svg [328 bytes]
│       ├── person-chalkboard-solid.svg [694 bytes]
│       ├── plus-circle.svg [251 bytes]
│       ├── quote.svg [490 bytes]
│       ├── rotate-ccw.svg [301 bytes]
│       ├── square-check.svg [296 bytes]
│       ├── star.svg [602 bytes]
│       ├── trash.svg [404 bytes]
│       ├── triangle-exclamation-solid.svg [542 bytes]
│       ├── user-check.svg [339 bytes]
│       ├── user-graduate-solid.svg [786 bytes]
│       ├── user-plus.svg [373 bytes]
│       └── user-tie-solid.svg [643 bytes]
├── components/
│   ├── answer_types/
│   │   ├── boolean.php [2.75 KB]
│   │   ├── essay-input.php [2.76 KB]
│   │   ├── essay.php [322 bytes]
│   │   ├── evaluation.php [1.42 KB]
│   │   ├── floating-input.php [2.24 KB]
│   │   └── multiple-choice.php [1.65 KB]
│   ├── ComponentsStyles.css [2.11 KB]
│   ├── footer.html [172 bytes]
│   ├── footer.php [214 bytes]
│   ├── header.html [624 bytes]
│   ├── header.php [1.81 KB]
│   ├── navigation.php [2.03 KB]
│   ├── separator.html [219 bytes]
│   └── teacher_navigation.php [1.41 KB]
├── config/
│   ├── DbConnection.php [572 bytes]
│   ├── dbConnectionCit.php [388 bytes]
│   └── paths.php [165 bytes]
├── evaluation/
│   ├── config/
│   │   └── answer_processor.php [2.09 KB]
│   ├── error_log [10.06 KB]
│   ├── evaluation-thankyou.php [1.80 KB]
│   ├── form-header.php [2.37 KB]
│   └── submit.php [NEW - Unified Entry Point]
├── database/
│   ├── migrations/
│   │   ├── 001_dynamic_system_tables.sql [1.50 KB]
│   │   └── 002_add_password_and_access_fields.sql [598 bytes]
│   ├── citcoder_Quality.sql [6.41 KB]
│   └── migrate_constants.php [2.50 KB]
├── forms/
│   ├── add/
│   │   ├── add-default-question.php [915 bytes]
│   │   ├── add-new-section.php [834 bytes]
│   │   └── add-question-option.php [1.11 KB]
│   ├── delete/
│   │   ├── delete-form.php [1.85 KB]
│   │   ├── delete-question.php [809 bytes]
│   │   ├── delete-section.php [803 bytes]
│   │   └── remove-question-option.php [1.18 KB]
│   ├── update/
│   │   ├── update-form-note.php [970 bytes]
│   │   ├── update-form-status.php [1018 bytes]
│   │   ├── update-form-target.php [1.05 KB]
│   │   ├── update-form-type.php [1.03 KB]
│   │   ├── update-form.php [1016 bytes]
│   │   ├── update-question-type.php [1.07 KB]
│   │   ├── update-question.php [1.00 KB]
│   │   └── update-section.php [1016 bytes]
│   ├── create-form.php [3.12 KB]
│   ├── edit-form.php [36.82 KB] (includes type/password/field management)
│   └── form_constants.php [1.28 KB]
├── helpers/
│   ├── database.php [1.39 KB]
│   ├── FormTypes.php [NEW - type loading helper]
│   ├── ResponseHandler.php [NEW - Unified Logic]
│   └── units.php [1.97 KB]
├── members/
│   ├── delete/
│   │   └── delete_admin.php [933 bytes]
│   ├── update/
│   │   └── update_permission.php [440 bytes]
│   └── create-admin.php [6.18 KB]
├── scripts/
│   ├── lib/
│   │   ├── reportGenerator.js [10.01 KB]
│   │   └── utils.js [2.12 KB]
│   ├── dashbord.js [8.67 KB]
│   ├── evaluation-form.js [3.67 KB]
│   ├── forms.js [26.08 KB]
│   ├── main.js [773 bytes]
│   ├── members.js [4.22 KB]
│   ├── statistics.js [7.99 KB]
│   ├── teacher_dashboard.js [5.25 KB]
│   └── teacher_statistics.js [5.25 KB]
├── statistics/
│   ├── analytics/
│   │   ├── assets/
│   │   │   ├── css/
│   │   │   │   ├── base/
│   │   │   │   │   ├── reset.css [0 bytes]
│   │   │   │   │   ├── typography.css [755 bytes]
│   │   │   │   │   └── variables.css [402 bytes]
│   │   │   │   ├── components/
│   │   │   │   │   ├── buttons.css [1.19 KB]
│   │   │   │   │   ├── cards.css [785 bytes]
│   │   │   │   │   ├── charts.css [717 bytes]
│   │   │   │   │   ├── forms.css [248 bytes]
│   │   │   │   │   ├── loader.css [292 bytes]
│   │   │   │   │   └── texts.css [476 bytes]
│   │   │   │   ├── utilities/
│   │   │   │   │   ├── layout.css [794 bytes]
│   │   │   │   │   ├── responsive.css [1.14 KB]
│   │   │   │   │   └── spacing.css [642 bytes]
│   │   │   │   └── main.css [396 bytes]
│   │   │   └── js/
│   │   │       ├── CSVReportGenerator.js [3.39 KB]
│   │   │       ├── ExcelReportGenerator.js [3.03 KB]
│   │   │       ├── PDFReportGenerator.js [14.78 KB]
│   │   │       ├── ZIPReportGenerator.js [793 bytes]
│   │   │       ├── chart-utilities.js [5.51 KB]
│   │   │       ├── doughnut-chart-utilities.js [4.48 KB]
│   │   │       └── download-utilities.js [22.54 KB]
│   │   ├── shared/
│   │   │   ├── auth.php [146 bytes]
│   │   │   ├── chart_helper.php [644 bytes]
│   │   │   ├── data_fetcher.php [4.09 KB]
│   │   │   ├── database.php [1.08 KB]
│   │   │   ├── head.php [741 bytes]
│   │   │   ├── header.php [111 bytes]
│   │   │   ├── scripts.php [449 bytes]
│   │   │   └── utilities.php [1020 bytes]
│   │   └── targets/
│   │       ├── alumni/
│   │       │   └── types/
│   │       │       ├── get_participants.php [1021 bytes]
│   │       │       └── program_evaluation.php [8.74 KB]
│   │       ├── student/
│   │       │   └── types/
│   │       │       ├── course_evaluation.php [10.06 KB]
│   │       │       ├── facility_evaluation.php [5.34 KB]
│   │       │       ├── program_evaluation.php [5.66 KB]
│   │       │       └── teacher_evaluation.php [14.84 KB]
│   │       └── views/
│   │           ├── course_evaluation.php [11.58 KB]
│   │           ├── program_evaluation.php [12.92 KB]
│   │           └── teacher_evaluation.php [13.16 KB]
│   ├── questions/
│   │   ├── essay.php [3.25 KB]
│   │   ├── evaluation.php [5.19 KB]
│   │   ├── multiple_choice.php [2.81 KB]
│   │   └── true_false.php [2.67 KB]
│   ├── get_statistics.php [29.01 KB]
│   ├── get_teacher_statistics.php [10.73 KB]
│   └── router.php [1.09 KB]
├── styles/
│   ├── evaluation-form.css [9.07 KB]
│   ├── forms.css [27.10 KB]
│   ├── global.css [13.13 KB]
│   ├── login.css [3.55 KB]
│   ├── members.css [10.05 KB]
│   ├── print.css [532 bytes]
│   ├── statistics.css [10.72 KB]
│   └── utils.css [37 bytes]
├── ARCHITECTURE.md [26.27 KB]
├── README.md [11.51 KB]
├── dashboard.php [13.09 KB]
├── evaluation-form.php [13.52 KB] (with auth check)
├── folder-structure.md [10.28 KB]
├── forms.php [10.35 KB]
├── index.php [39 bytes]
├── login-form.php [6.82 KB] (NEW - password/field collection)
├── login.php [3.75 KB]
├── logout.php [596 bytes]
├── members.php [11.36 KB]
├── statistics.php [4.85 KB]
├── teacher_dashboard.php [7.97 KB]
└── teacher_statistics.php [3.73 KB]
```


### Summary

```plaintext
Root Folder: quality-system
Total Folders: 36
Total Files: 177
File Types:
  - .php Files: 77
  - .md Files: 3
  - .sql Files: 3
  - .ttf Files: 12
  - .svg Files: 42
  - .png Files: 1
  - .css Files: 22
  - .html Files: 3
  - No Extension Files: 1
  - .js Files: 17
Largest File: NotoKufiArabic-ExtraBold.ttf [176.69 KB]
Smallest File: reset.css [0 bytes]
Total Project Size: 2.71 MB
Ignored Files and Folders:
  - error_log
```
