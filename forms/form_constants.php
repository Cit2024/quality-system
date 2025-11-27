<?php
// forms/form_constants.php

define('FORM_TYPES', [
    'course_evaluation' => [
        'name' => 'تقييم مقرر',
        'icon' => './assets/icons/book-bookmark-solid.svg',
        'allowed_targets' => ['student', 'teacher', 'alumni']
    ],
    'teacher_evaluation' => [
        'name' => 'تقييم مدرس',
        'icon' => './assets/icons/person-chalkboard-solid.svg',
        'allowed_targets' => ['student', 'admin']
    ],
    'program_evaluation' => [
        'name' => 'تقييم برنامج',
        'icon' => './assets/icons/layer-group-solid.svg',
        'allowed_targets' => ['student', 'alumni', 'employer']
    ],
    'facility_evaluation' => [
        'name' => 'تقييم مؤسسة',
        'icon' => './assets/icons/building-solid.svg',
        'allowed_targets' => ['student', 'teacher']
    ],
    'leaders_evaluation' => [
        'name' => 'تقييم قيادات',
        'icon' => './assets/icons/user-tie-solid.svg',
        'allowed_targets' => ['teacher']
    ]
]);

define('FORM_TARGETS', [
    'student' => [
        'name' => 'طالب',
        'icon' => './assets/icons/graduation-cap-solid.svg'
    ],
    'teacher' => [
        'name' => 'مدرس',
        'icon' => './assets/icons/chalkboard-user-solid.svg'
    ],
    'admin' => [
        'name' => 'إداري',
        'icon' => './assets/icons/user-tie-solid.svg'
    ],
    'alumni' => [
        'name' => 'خريج',
        'icon' => './assets/icons/user-graduate-solid.svg'
    ],
    'employer' => [
        'name' => 'موظف',
        'icon' => './assets/icons/briefcase-solid.svg'
    ]
]);

define('TYPE_QUESTION',[
    'true_false' => [
        'name' => 'صح/خطأ',
        'icon' => './assets/icons/square-check.svg'
    ],
    'evaluation' => [
        'name' => 'تقييم',
        'icon' => './assets/icons/star.svg'
    ],
    'multiple_choice' => [
        'name' => 'إختيار من متعدد',
        'icon' => './assets/icons/list-check.svg'
    ],
    'essay' => [
        'name' => 'مقالي',
        'icon' => './assets/icons/quote.svg'
    ]
]);

