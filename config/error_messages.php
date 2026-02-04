<?php
/**
 * User-Facing Error Messages
 * Arabic error messages for all exception types
 */

return [
    // Validation Errors (400)
    'validation' => [
        'required' => 'هذا الحقل مطلوب',
        'invalid_email' => 'البريد الإلكتروني غير صحيح',
        'invalid_slug' => 'المعرف يجب أن يحتوي على أحرف إنجليزية وأرقام وشرطات سفلية فقط',
        'invalid_format' => 'تنسيق البيانات غير صحيح',
        'too_long' => 'القيمة المدخلة طويلة جداً',
        'too_short' => 'القيمة المدخلة قصيرة جداً',
        'invalid_number' => 'يجب إدخال رقم صحيح',
        'invalid_date' => 'التاريخ المدخل غير صحيح',
        'missing_parameters' => 'بعض البيانات المطلوبة مفقودة',
        'invalid_json' => 'البيانات المرسلة تالفة',
    ],
    
    // Authentication Errors (401)
    'auth' => [
        'invalid_credentials' => 'اسم المستخدم أو كلمة المرور غير صحيحة',
        'session_expired' => 'انتهت صلاحية الجلسة، يرجى تسجيل الدخول مرة أخرى',
        'not_authenticated' => 'يجب تسجيل الدخول للوصول إلى هذه الصفحة',
        'invalid_token' => 'رمز المصادقة غير صحيح',
        'account_locked' => 'تم قفل الحساب، يرجى التواصل مع المسؤول',
        'password_expired' => 'انتهت صلاحية كلمة المرور، يرجى تحديثها',
    ],
    
    // Permission Errors (403)
    'permission' => [
        'access_denied' => 'ليس لديك صلاحية للوصول إلى هذه الصفحة',
        'cannot_create' => 'ليس لديك صلاحية لإنشاء عناصر جديدة',
        'cannot_update' => 'ليس لديك صلاحية لتعديل هذا العنصر',
        'cannot_delete' => 'ليس لديك صلاحية لحذف هذا العنصر',
        'cannot_view' => 'ليس لديك صلاحية لعرض هذا العنصر',
        'insufficient_permissions' => 'صلاحياتك غير كافية لتنفيذ هذا الإجراء',
    ],
    
    // Not Found Errors (404)
    'not_found' => [
        'form_not_found' => 'النموذج غير موجود أو تم حذفه',
        'question_not_found' => 'السؤال غير موجود',
        'section_not_found' => 'القسم غير موجود',
        'user_not_found' => 'المستخدم غير موجود',
        'resource_not_found' => 'العنصر المطلوب غير موجود',
        'page_not_found' => 'الصفحة غير موجودة',
        'invalid_evaluator' => 'نوع المقيم غير صحيح',
        'invalid_evaluation' => 'نوع التقييم غير صحيح',
    ],
    
    // Duplicate/Conflict Errors (409)
    'duplicate' => [
        'already_submitted' => 'لقد قمت بتقديم هذا النموذج مسبقاً',
        'duplicate_entry' => 'هذا العنصر موجود مسبقاً',
        'slug_exists' => 'هذا المعرف مستخدم بالفعل',
        'email_exists' => 'هذا البريد الإلكتروني مسجل مسبقاً',
    ],
    
    // Form-Specific Errors
    'form' => [
        'not_published' => 'هذا النموذج غير منشور حالياً',
        'expired' => 'انتهت صلاحية هذا النموذج',
        'invalid_type_combination' => 'نوع التقييم والمقيم غير متوافقين',
        'no_sections' => 'النموذج لا يحتوي على أي أقسام',
        'no_questions' => 'القسم لا يحتوي على أي أسئلة',
        'password_required' => 'هذا النموذج محمي بكلمة مرور',
        'invalid_password' => 'كلمة المرور غير صحيحة',
        'missing_required_fields' => 'يرجى ملء جميع الحقول المطلوبة',
    ],
    
    // Database Errors (500)
    'database' => [
        'connection_error' => 'خطأ في الاتصال بقاعدة البيانات، يرجى المحاولة لاحقاً',
        'query_error' => 'حدث خطأ أثناء معالجة البيانات',
        'transaction_failed' => 'فشلت العملية، يرجى المحاولة مرة أخرى',
        'constraint_violation' => 'لا يمكن تنفيذ هذا الإجراء بسبب ارتباطات موجودة',
    ],
    
    // General System Errors (500)
    'system' => [
        'internal_error' => 'حدث خطأ داخلي في النظام',
        'service_unavailable' => 'الخدمة غير متاحة حالياً، يرجى المحاولة لاحقاً',
        'maintenance_mode' => 'النظام قيد الصيانة، يرجى المحاولة لاحقاً',
        'file_upload_error' => 'فشل رفع الملف',
        'file_too_large' => 'حجم الملف كبير جداً',
    ],
    
    // Success Messages
    'success' => [
        'created' => 'تم الإنشاء بنجاح',
        'updated' => 'تم التحديث بنجاح',
        'deleted' => 'تم الحذف بنجاح',
        'submitted' => 'تم الإرسال بنجاح',
        'saved' => 'تم الحفظ بنجاح',
        'login_success' => 'تم تسجيل الدخول بنجاح',
        'logout_success' => 'تم تسجيل الخروج بنجاح',
    ],
    
    // Action Guidance
    'guidance' => [
        'contact_admin' => 'إذا استمرت المشكلة، يرجى التواصل مع المسؤول',
        'try_again' => 'يرجى المحاولة مرة أخرى',
        'check_input' => 'يرجى التحقق من البيانات المدخلة',
        'refresh_page' => 'يرجى تحديث الصفحة والمحاولة مرة أخرى',
        'login_required' => 'يرجى تسجيل الدخول أولاً',
    ],
];
?>
